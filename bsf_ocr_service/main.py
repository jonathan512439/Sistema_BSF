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
    Heurísticas simples para extraer:
      - titulo  : primera línea no vacía
      - fecha   : primer patrón tipo YYYY-MM-DD, DD/MM/YYYY o DD-MM-YYYY
      - gestion : primer año de 4 dígitos razonable
      - oficial : línea que parezca contener rango/nombre de oficial
    """
    lines = [l.strip() for l in text.splitlines() if l.strip()]

    titulo = lines[0] if lines else ""

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

    gestion = None
    m = re.search(r"\b(19\d{2}|20\d{2}|2100)\b", text)
    if m:
        gestion = m.group(1)

    oficial = None
    patrones_oficial = [r"Sgto", r"Cbo", r"Oficial", r"Suboficial", r"Tte\.", r"Cap\."]
    for line in lines:
        if any(pat in line for pat in patrones_oficial):
            oficial = line
            break

    return {
        "titulo": titulo,
        "fecha": fecha,
        "gestion": gestion,
        "oficial": oficial,
    }


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
