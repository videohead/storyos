@echo off
setlocal EnableExtensions EnableDelayedExpansion

set "ROOT_DIR=%~dp0"
set "COMFY_DIR=%ROOT_DIR%..\ComfyUI"
set "SMALLMOE_DIR=%ROOT_DIR%..\smallMOE"

echo ============================================================
echo World Graph Studio startup and verification
echo ============================================================

echo.
echo [STEP 1/3] Starting ComfyUI Docker stack...
pushd "%COMFY_DIR%"
docker compose up -d --build
if errorlevel 1 (
    echo ERROR: ComfyUI Docker stack failed to start.
    popd
    exit /b 1
)
call :WaitForHttp "http://localhost:8188/" "ComfyUI"
if errorlevel 1 (
    echo ERROR: ComfyUI did not become reachable on port 8188.
    popd
    exit /b 1
)
popd

echo.
echo [STEP 2/3] Starting smallMOE Docker stack...
pushd "%SMALLMOE_DIR%"
docker compose up -d
if errorlevel 1 (
    echo ERROR: smallMOE Docker stack failed to start.
    popd
    exit /b 1
)
call :WaitForHttp "http://localhost:11434/v1/models" "smallMOE LLM"
if errorlevel 1 (
    echo ERROR: smallMOE LLM did not become reachable on port 11434.
    popd
    exit /b 1
)
popd

echo.
echo [STEP 3/3] Starting Lando in local World Graph Studio directory...
pushd "%ROOT_DIR%"
lando start
if errorlevel 1 (
    echo ERROR: Lando failed to start the World Graph Studio site.
    popd
    exit /b 1
)
lando info
if errorlevel 1 (
    echo ERROR: Lando info verification failed.
    popd
    exit /b 1
)
popd

echo.
echo All required services were launched and verified successfully.
echo ComfyUI: http://localhost:8188/
echo smallMOE: http://localhost:11434/v1/models
echo World Graph Studio Lando site: check 'lando info'
exit /b 0

:WaitForHttp
set "URL=%~1"
set "LABEL=%~2"
for /L %%I in (1,1,60) do (
    powershell -NoProfile -Command "$ProgressPreference='SilentlyContinue'; try { $r = Invoke-WebRequest -UseBasicParsing '%URL%' -TimeoutSec 5; if ($r.StatusCode -ge 200 -and $r.StatusCode -lt 500) { exit 0 } } catch { } exit 1" >nul 2>&1
    if not errorlevel 1 (
        echo %LABEL% is reachable at %URL%
        exit /b 0
    )
    ping -n 2 127.0.0.1 >nul
)
exit /b 1
