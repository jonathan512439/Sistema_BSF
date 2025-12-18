from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import Dict, Any, Optional, Tuple, List
import pytesseract
from pdf2image import convert_from_path
import os
import re

# Ruta explícita de Tesseract en Windows
pytesseract.pytesseract.tesseract_cmd = r"C:\Tesseract-OCR\tesseract.exe"

# Ruta explícita de Poppler en Windows (según tu instalación)
POPPLER_PATH = r"C:\poppler\Library\bin"

app = FastAPI(title="BSF OCR Service", version="1.0.0")


class OcrRequest(BaseModel):
    pdf_path: str
    idioma: Optional[str] = "spa"
    documento_id: Optional[int] = None


class OcrResponse(BaseModel):
    ok: bool
    engine: str
    confidence_media: Optional[float]
    fields: Dict[str, Any]
    full_text: str


def extract_text_from_pdf(pdf_path: str, idioma: str = "spa") -> Tuple[str, Optional[float]]:
    """
    Convierte el PDF a imágenes y aplica Tesseract.
    Devuelve (texto_completo, confianza_media_aproximada).
    """
    if not os.path.exists(pdf_path):
        raise FileNotFoundError(f"PDF no encontrado: {pdf_path}")

    try:
        pages = convert_from_path(
            pdf_path,
            dpi=300,
            poppler_path=POPPLER_PATH
        )
    except Exception as e:
        raise RuntimeError(f"No se pudo convertir el PDF a imágenes: {e}")

    if not pages:
        raise RuntimeError("El PDF no tiene páginas o no se pudo convertir correctamente.")

    all_text: List[str] = []
    confidences: List[float] = []

    for page in pages:
        txt = pytesseract.image_to_string(page, lang=idioma)
        all_text.append(txt)

        data = pytesseract.image_to_data(page, lang=idioma, output_type=pytesseract.Output.DICT)
        for conf in data.get("conf", []):
            try:
                c = float(conf)
                if c >= 0:
                    confidences.append(c)
            except (ValueError, TypeError):
                continue

    full_text = "\n".join(all_text).strip()
    conf_media: Optional[float] = (
        sum(confidences) / len(confidences) if confidences else None
    )
    return full_text, conf_media


def extract_fields_from_text(text: str) -> Dict[str, Any]:
    """
    Heurísticas para extraer metadatos del documento:
      - titulo: primera línea no vacía
      - fecha: primer patrón tipo YYYY-MM-DD, DD/MM/YYYY o DD-MM-YYYY
      - gestion: primer año de 4 dígitos razonable
      - oficial: línea que parezca contener rango/nombre de oficial
      - tipo_documento: clasificación basada en palabras clave
      - seccion: inferencia basada en contenido
      - subseccion: inferencia basada en sección y contenido
      - descripcion: resumen del contenido
    """
    lines = [l.strip() for l in text.splitlines() if l.strip()]
    text_lower = text.lower()

    # Título: primera línea significativa
    titulo = lines[0] if lines else ""

    # Fecha
    fecha = None
    patrones_fecha = [
        r"\b(\d{4})-(\d{2})-(\d{2})\b",
        r"\b(\d{1,2})/(\d{1,2})/(\d{4})\b",
        r"\b(\d{1,2})-(\d{1,2})-(\d{4})\b",
    ]
    for line in lines:
        for pat in patrones_fecha:
            m = re.search(pat, line)
            if m:
                fecha = m.group(0)
                break
        if fecha:
            break

    # Gestión: primer año encontrado
    gestion = None
    m = re.search(r"\b(19\d{2}|20\d{2}|2100)\b", text)
    if m:
        gestion = m.group(1)

    # Oficial
    oficial = None
    patrones_oficial = [r"Sgto", r"Cbo", r"Oficial", r"Suboficial", r"Tte\.", r"Cap\."]
    for line in lines:
        if any(pat in line for pat in patrones_oficial):
            oficial = line
            break

    # Clasificación de Tipo de Documento
    tipo_documento = classify_document_type(text_lower)

    # Inferencia de Sección
    seccion = infer_section(text_lower)

    # Inferencia de Subsección (basada en sección)
    subseccion = infer_subsection(text_lower, seccion)

    # Generar descripción
    descripcion = generate_description(lines, titulo)

    return {
        "titulo": titulo,
        "fecha": fecha,
        "gestion": gestion,
        "oficial": oficial,
        "tipo_documento": tipo_documento,
        "seccion": seccion,
        "subseccion": subseccion,
        "descripcion": descripcion,
    }


def classify_document_type(text_lower: str) -> Optional[str]:
    """
    Clasifica el tipo de documento basándose en palabras clave.
    Retorna el nombre del tipo sugerido o None.
    """
    # Diccionario de patrones: tipo -> lista de palabras clave
    patterns = {
        "Acta": ["acta", "reunión", "reunion", "sesión", "sesion"],
        "Memorándum": ["memorándum", "memorandum", "memo"],
        "Resolución": ["resolución", "resolucion", "resuelve"],
        "Informe": ["informe", "reporte"],
        "Certificado": ["certificado", "certifica", "certificación", "certificacion"],
        "Carta": ["carta", "nota"],
        "Oficio": ["oficio"],
        "Circular": ["circular"],
        "Decreto": ["decreto"],
        "Orden": ["orden del día", "orden del dia"],
        "Contrato": ["contrato", "convenio"],
        "Manual": ["manual", "procedimiento"],
        "Reglamento": ["reglamento", "normativa"],
        "Plan": ["plan", "planificación", "planificacion"],
        "Proyecto": ["proyecto"],
    }

    # Contar coincidencias para cada tipo
    scores = {}
    for doc_type, keywords in patterns.items():
        score = sum(1 for keyword in keywords if keyword in text_lower)
        if score > 0:
            scores[doc_type] = score

    # Retornar el tipo con mayor score
    if scores:
        return max(scores, key=scores.get)
    return None


def infer_section(text_lower: str) -> Optional[str]:
    """
    Infiere la sección del documento basándose en palabras clave.
    """
    patterns = {
        "Logística": ["logística", "logistica", "abastecimiento", "suministro", "almacén", "almacen"],
        "Personal": ["recursos humanos", "personal", "rrhh", "planilla", "empleado"],
        "Legal": ["legal", "jurídico", "juridico", "asesoría legal", "asesoria legal"],
        "Finanzas": ["finanzas", "contabilidad", "presupuesto", "financiero"],
        "Operaciones": ["operaciones", "operativo", "actividades"],
        "Administración": ["administración", "administracion", "administrativo", "gestión", "gestion"],
        "Tecnología": ["tecnología", "tecnologia", "sistemas", "informática", "informatica", "ti"],
        "Salud": ["salud", "médico", "medico", "hospital", "clínica", "clinica"],
        "Seguridad": ["seguridad", "vigilancia", "custodia"],
        "Comunicación": ["comunicación", "comunicacion", "prensa", "relaciones públicas", "relaciones publicas"],
        "Capacitación": ["capacitación", "capacitacion", "formación", "formacion", "entrenamiento"],
    }

    scores = {}
    for section, keywords in patterns.items():
        score = sum(1 for keyword in keywords if keyword in text_lower)
        if score > 0:
            scores[section] = score

    if scores:
        return max(scores, key=scores.get)
    return None


def infer_subsection(text_lower: str, seccion: Optional[str]) -> Optional[str]:
    """
    Infiere la subsección basándose en la sección y contenido.
    """
    # Patrones de subsección específicos por sección
    subsection_patterns = {
        "Logística": {
            "Inventario": ["inventario", "stock", "existencias"],
            "Compras": ["compras", "adquisición", "adquisicion", "proveedor"],
            "Distribución": ["distribución", "distribucion", "entrega", "despacho"],
        },
        "Personal": {
            "Contratación": ["contratación", "contratacion", "reclutamiento", "selección", "seleccion"],
            "Capacitación": ["capacitación", "capacitacion", "formación", "formacion"],
            "Evaluación": ["evaluación", "evaluacion", "desempeño", "desempeno"],
        },
        "Finanzas": {
            "Presupuesto": ["presupuesto", "asignación", "asignacion"],
            "Contabilidad": ["contabilidad", "balance", "estados financieros"],
            "Tesorería": ["tesorería", "tesoreria", "caja", "pagos"],
        },
        "Legal": {
            "Contratos": ["contrato", "convenio"],
            "Litigios": ["litigio", "demanda", "juicio"],
            "Normativa": ["normativa", "reglamento", "ley"],
        },
    }

    if not seccion or seccion not in subsection_patterns:
        return None

    patterns = subsection_patterns[seccion]
    scores = {}
    for subsection, keywords in patterns.items():
        score = sum(1 for keyword in keywords if keyword in text_lower)
        if score > 0:
            scores[subsection] = score

    if scores:
        return max(scores, key=scores.get)
    return None


def generate_description(lines: List[str], titulo: str) -> str:
    """
    Genera una descripción automática del documento.
    Usa las primeras líneas significativas o repite el título.
    """
    # Intentar obtener las primeras 2-3 líneas significativas
    significant_lines = []
    for line in lines[:5]:  # Revisar las primeras 5 líneas
        # Ignorar líneas muy cortas o que parezcan encabezados/fechas
        if len(line) > 20 and not re.match(r'^[\d\s/\-:]+$', line):
            significant_lines.append(line)
        if len(significant_lines) >= 2:
            break

    if significant_lines:
        descripcion = " ".join(significant_lines)
        # Limitar a 200 caracteres
        if len(descripcion) > 200:
            descripcion = descripcion[:197] + "..."
        return descripcion
    
    # Si no hay líneas significativas, usar el título
    if titulo:
        return titulo
    
    return "Documento sin descripción disponible"


# 🔹 Página de prueba rápida
@app.get("/")
def root():
    return {"ok": True, "service": "bsf-ocr", "version": "1.0.0"}


# 🔹 Un mismo handler registrado en varias rutas para máxima compatibilidad
@app.post("/api/ocr/documento", response_model=OcrResponse)
@app.post("/ocr/documento", response_model=OcrResponse)
@app.post("/ocr/document", response_model=OcrResponse)
def ocr_documento(req: OcrRequest):
    """
    Procesa un PDF en la ruta dada y devuelve:
      - texto completo
      - confianza media aproximada
      - algunos campos sugeridos (titulo, fecha, gestion, oficial)
    """
    try:
        full_text, conf_media = extract_text_from_pdf(req.pdf_path, req.idioma or "spa")
        fields = extract_fields_from_text(full_text)

        return OcrResponse(
            ok=True,
            engine="tesseract",
            confidence_media=conf_media,
            fields=fields,
            full_text=full_text,
        )
    except FileNotFoundError as e:
        raise HTTPException(status_code=404, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"OCR error: {e}")
