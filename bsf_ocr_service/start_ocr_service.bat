@echo off
echo Iniciando servicio OCR BSF...
echo.

cd /d "d:\Jonathan\Desktop\INFORMATICA\INF3811-ISW2\BSF-docs\bsf_ocr_service"

REM Activar entorno virtual si existe
if exist "venv\Scripts\activate.bat" (
    echo Activando entorno virtual...
    call venv\Scripts\activate.bat
)

REM Iniciar el servicio con uvicorn
echo Iniciando FastAPI con Uvicorn en puerto 8001...
python -m uvicorn main:app --host 0.0.0.0 --port 8001 --reload
