param(
    [ValidateSet('gui', 'tui')]
    [string]$Modo = 'gui',
    [int]$Espera = 10
)

$ErrorActionPreference = 'Stop'

# ...\TRANSNUBET  (este script vive en ...\root\phpinfo)
$raiz   = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$exeGui = Join-Path $raiz 'herramientas\Serial Generator\VB6\Licencia_SISGESNOM\Licencia_SISGESNOM.exe'
$cmdTui = Join-Path $raiz 'herramientas\Serial Generator\Terminal\Licencia_SISGESNOM.cmd'

Add-Type -Namespace SG -Name U -MemberDefinition @'
[DllImport("user32.dll")] public static extern bool EnumWindows(EnumWindowsProc cb, IntPtr l);
public delegate bool EnumWindowsProc(IntPtr hWnd, IntPtr lParam);
[DllImport("user32.dll")] public static extern uint GetWindowThreadProcessId(IntPtr hWnd, out uint pid);
[DllImport("user32.dll")] public static extern int GetWindowText(IntPtr hWnd, System.Text.StringBuilder s, int n);
[DllImport("user32.dll")] public static extern int GetClassName(IntPtr hWnd, System.Text.StringBuilder s, int n);
[DllImport("user32.dll")] public static extern bool GetWindowRect(IntPtr hWnd, out RECT r);
[DllImport("user32.dll")] public static extern bool IsWindowVisible(IntPtr hWnd);
[DllImport("user32.dll")] public static extern bool IsIconic(IntPtr hWnd);
[DllImport("user32.dll")] public static extern bool ShowWindow(IntPtr hWnd, int cmd);
[DllImport("user32.dll")] public static extern bool SetForegroundWindow(IntPtr hWnd);
[DllImport("user32.dll")] public static extern bool BringWindowToTop(IntPtr hWnd);
[DllImport("user32.dll")] public static extern bool AttachThreadInput(uint a, uint b, bool f);
[DllImport("user32.dll")] public static extern IntPtr GetForegroundWindow();
[DllImport("user32.dll")] public static extern bool SetWindowPos(IntPtr hWnd, IntPtr hAfter, int x, int y, int cx, int cy, uint flags);
[DllImport("user32.dll")] public static extern void keybd_event(byte vk, byte scan, uint flags, UIntPtr extra);
public struct RECT { public int Left, Top, Right, Bottom; }
'@

# --------------------------------------------------------------------------
# JSON a prueba de codificaciones: todo lo que no sea ASCII sale escapado,
# así lo que llegue a PHP siempre es json_decode() válido.
function Esc([string]$texto) {
    if ($null -eq $texto) { return '' }
    $sb = New-Object System.Text.StringBuilder
    foreach ($ch in $texto.ToCharArray()) {
        $code = [int]$ch
        if ($code -lt 32 -or $code -gt 126) {
            [void]$sb.Append(('\u{0:x4}' -f $code))
        }
        elseif ($ch -eq [char]'"') { [void]$sb.Append('\"') }
        elseif ($ch -eq [char]'\') { [void]$sb.Append('\\') }
        else { [void]$sb.Append($ch) }
    }
    return $sb.ToString()
}

function Responder([string]$Estado, [string]$Mensaje, [bool]$Frente, [int]$ProcId, [string]$Titulo) {
    $ok = $Frente -or ($Estado -in @('abierta', 'restaurada'))
    $json = '{"ok":' + $(if ($ok) { 'true' } else { 'false' }) +
            ',"estado":"' + (Esc $Estado) + '"' +
            ',"frente":' + $(if ($Frente) { 'true' } else { 'false' }) +
            ',"pid":' + $ProcId +
            ',"titulo":"' + (Esc $Titulo) + '"' +
            ',"mensaje":"' + (Esc $Mensaje) + '"}'
    [Console]::Out.Write($json)
    exit 0
}

function Get-VentanasProceso([int]$ProcId) {
    $salida = New-Object System.Collections.ArrayList
    $objetivo = $ProcId
    $cb = [SG.U+EnumWindowsProc]{
        param($h, $l)
        $otro = 0
        [SG.U]::GetWindowThreadProcessId($h, [ref]$otro) | Out-Null
        if ($otro -eq $objetivo) {
            $c = New-Object System.Text.StringBuilder 256
            [SG.U]::GetClassName($h, $c, 256) | Out-Null
            $t = New-Object System.Text.StringBuilder 512
            [SG.U]::GetWindowText($h, $t, 512) | Out-Null
            $r = New-Object SG.U+RECT
            [SG.U]::GetWindowRect($h, [ref]$r) | Out-Null
            [void]$salida.Add([pscustomobject]@{
                HWND    = [IntPtr]$h
                Clase   = $c.ToString()
                Titulo  = $t.ToString()
                Visible = [SG.U]::IsWindowVisible($h)
                Ancho   = $r.Right - $r.Left
                Alto    = $r.Bottom - $r.Top
            })
        }
        return $true
    }
    [SG.U]::EnumWindows($cb, [IntPtr]::Zero) | Out-Null
    return $salida
}

# Ventana "de verdad": visible y con tamaño (la auxiliar de VB6 mide 0x0).
function Get-VentanaReal($ventanas, [string[]]$clases) {
    foreach ($v in $ventanas) {
        if (-not $v.Visible) { continue }
        if ($v.Ancho -le 120 -or $v.Alto -le 120) { continue }
        if ($clases.Count -gt 0 -and ($clases -notcontains $v.Clase)) { continue }
        return $v
    }
    return $null
}

# Trae la ventana al frente sin teclas sintéticas: se engancha la cola de
# entrada del proceso que tiene el foco y se pide el primer plano. Después
# se comprueba que de verdad lo tenga y se reintenta un par de veces.
function Set-VentanaAlFrente([IntPtr]$HWnd) {
    if ([SG.U]::IsIconic($HWnd)) { [void][SG.U]::ShowWindow($HWnd, 9) }   # SW_RESTORE
    else { [void][SG.U]::ShowWindow($HWnd, 5) }                            # SW_SHOW

    # Z-order: pasar por "siempre visible" un instante y soltarlo deja la
    # ventana por encima del navegador aunque el foco no se pueda robar.
    $flags = 0x0001 -bor 0x0002 -bor 0x0040   # NOSIZE | NOMOVE | SHOWWINDOW
    [void][SG.U]::SetWindowPos($HWnd, [IntPtr](-1), 0, 0, 0, 0, $flags)
    [void][SG.U]::SetWindowPos($HWnd, [IntPtr](-2), 0, 0, 0, 0, $flags)

    for ($intento = 0; $intento -lt 6; $intento++) {
        if ([SG.U]::GetForegroundWindow() -eq $HWnd) { return $true }

        # Windows sólo deja robar el primer plano al proceso que "acaba de
        # recibir entrada": F24 es inofensiva (ninguna app la usa), a diferencia
        # de ALT, que abría el menú del formulario.
        [SG.U]::keybd_event(0x87, 0, 0, [UIntPtr]::Zero)   # VK_F24 abajo
        [SG.U]::keybd_event(0x87, 0, 2, [UIntPtr]::Zero)   # VK_F24 arriba

        $hFrente = [SG.U]::GetForegroundWindow()
        $pidFrente = 0
        [void][SG.U]::GetWindowThreadProcessId($hFrente, [ref]$pidFrente)
        $hiloFrente = 0
        [void][SG.U]::GetWindowThreadProcessId($hFrente, [ref]$hiloFrente)
        $hiloMio = 0
        [void][SG.U]::GetWindowThreadProcessId($HWnd, [ref]$hiloMio)

        try {
            if ($hiloFrente -ne 0 -and $hiloFrente -ne $hiloMio) {
                [void][SG.U]::AttachThreadInput($hiloFrente, $hiloMio, $true)
            }
            [void][SG.U]::BringWindowToTop($HWnd)
            [void][SG.U]::SetForegroundWindow($HWnd)
        }
        finally {
            if ($hiloFrente -ne 0 -and $hiloFrente -ne $hiloMio) {
                [void][SG.U]::AttachThreadInput($hiloFrente, $hiloMio, $false)
            }
        }

        Start-Sleep -Milliseconds 250
        if ([SG.U]::GetForegroundWindow() -eq $HWnd) { return $true }
    }

    return $false
}

# ------------------------------------------------------------------- GUI
if ($Modo -eq 'gui') {
    if (-not (Test-Path $exeGui)) {
        Responder 'error' 'No se encontró Licencia_SISGESNOM.exe.' $false 0 ''
    }

    $clasesGui = @('ThunderRT6FormDC', 'ThunderRT6Main', 'ThunderRT6MDIForm')
    $procesos = @(Get-Process -Name 'Licencia_SISGESNOM' -ErrorAction SilentlyContinue)
    $ventana = $null
    $proc = $null
    $reutilizada = $false

    foreach ($p in $procesos) {
        $v = Get-VentanaReal (Get-VentanasProceso $p.Id) $clasesGui
        if ($v) { $ventana = $v; $proc = $p; $reutilizada = $true; break }
    }

    # Instancias zombis (vivas pero sin ventana): se limpian antes de relanzar.
    if (-not $proc -and $procesos.Count -gt 0) {
        foreach ($p in $procesos) { Stop-Process -Id $p.Id -Force -ErrorAction SilentlyContinue }
        Start-Sleep -Milliseconds 700
    }

    if (-not $proc) {
        $proc = Start-Process -FilePath $exeGui -WorkingDirectory (Split-Path $exeGui) -PassThru
        $limite = (Get-Date).AddSeconds($Espera)
        while ((Get-Date) -lt $limite) {
            Start-Sleep -Milliseconds 300
            if ($proc.HasExited) { break }
            $ventana = Get-VentanaReal (Get-VentanasProceso $proc.Id) $clasesGui
            if ($ventana) { break }
        }

        if (-not $ventana) {
            if ($proc.HasExited) {
                Responder 'sin-ventana' 'El proceso terminó sin abrir ninguna ventana.' $false $proc.Id ''
            }
            Responder 'sin-ventana' 'La aplicación arrancó pero no creó ninguna ventana visible.' $false $proc.Id ''
        }
    }

    $frente = Set-VentanaAlFrente $ventana.HWND
    $titulo = $ventana.Titulo

    if ($reutilizada) {
        $msg = if ($frente) {
            'La aplicación ya estaba abierta: se trajo al frente la ventana "' + $titulo + '".'
        } else {
            'La aplicación ya estaba abierta ("' + $titulo + '"), pero Windows no permitió ponerla al frente.'
        }
        Responder 'restaurada' $msg $frente $proc.Id $titulo
    }

    $msg = if ($frente) {
        'Serial Generador (GUI) abierto y al frente. Pide la Contraseña Universal de Proyecto Unicornio.'
    } else {
        'Serial Generador (GUI) abierto, pero Windows no permitió ponerlo al frente.'
    }
    Responder 'abierta' $msg $frente $proc.Id $titulo
}

# ------------------------------------------------------------------- TUI
if (-not (Test-Path $cmdTui)) {
    Responder 'error' 'No se encontró Licencia_SISGESNOM.cmd.' $false 0 ''
}

$script:consola = $null
$script:clasesConsola = @('ConsoleWindowClass', 'CASCADIA_HOSTING_WINDOW_CLASS', 'PseudoConsoleWindow', 'mintty')

# Busca la consola de la TUI: primero por título, si no, por "ventana nueva"
# (Windows Terminal abre pestañas dentro de su propia ventana).
function Buscar-Consola([string[]]$conocidas) {
    $script:consola = $null
    $candidatas = @()
    $cb = [SG.U+EnumWindowsProc]{
        param($h, $l)
        $c = New-Object System.Text.StringBuilder 256
        [SG.U]::GetClassName($h, $c, 256) | Out-Null
        if ($script:clasesConsola -contains $c.ToString() -and [SG.U]::IsWindowVisible($h)) {
            $t = New-Object System.Text.StringBuilder 512
            [SG.U]::GetWindowText($h, $t, 512) | Out-Null
            $r = New-Object SG.U+RECT
            [SG.U]::GetWindowRect($h, [ref]$r) | Out-Null
            if (($r.Right - $r.Left) -gt 120) {
                $script:candidatas += [pscustomobject]@{
                    HWND = [IntPtr]$h; Titulo = $t.ToString(); Clase = $c.ToString()
                }
            }
        }
        return $true
    }
    $script:candidatas = @()
    [SG.U]::EnumWindows($cb, [IntPtr]::Zero) | Out-Null

    # 1) por título
    foreach ($v in $script:candidatas) {
        if ($v.Titulo -match 'Licencia|SISGESNOM|Serial') { $script:consola = $v; break }
    }
    # 2) ventana de consola que no existía antes de lanzar
    if (-not $script:consola) {
        foreach ($v in $script:candidatas) {
            if ($conocidas -notcontains $v.HWND.ToInt64()) { $script:consola = $v; break }
        }
    }
    return $script:consola
}

$antes = @()
$cbAntes = [SG.U+EnumWindowsProc]{
    param($h, $l)
    $script:antes += $h.ToInt64()
    return $true
}
$script:antes = @()
[SG.U]::EnumWindows($cbAntes, [IntPtr]::Zero) | Out-Null
$claves = @($script:antes)

$lanzador = Join-Path $env:TEMP 'transnubet_tui.cmd'
$contenido = "@echo off`r`ntitle Serial Generador TUI`r`ncall `"$cmdTui`"`r`n"
[System.IO.File]::WriteAllText($lanzador, $contenido, [System.Text.Encoding]::Default)

$proc = Start-Process -FilePath 'cmd.exe' -ArgumentList '/k', ('"{0}"' -f $lanzador) `
                      -WorkingDirectory (Split-Path $cmdTui) -PassThru -WindowStyle Normal

$limite = (Get-Date).AddSeconds($Espera)
$consola = $null
while ((Get-Date) -lt $limite) {
    Start-Sleep -Milliseconds 300
    $consola = Buscar-Consola $claves
    if ($consola) { break }
}

if (-not $consola) {
    Responder 'abierta' 'La TUI se lanzó en la ventana de terminal (Windows no expuso una ventana nueva que se pueda enfocar).' $false $proc.Id ''
}

$frente = Set-VentanaAlFrente $consola.HWND
$msg = if ($frente) { 'Serial Generador (TUI) abierto y al frente en la ventana de terminal.' }
       else { 'Serial Generador (TUI) abierto, pero Windows no permitió ponerlo al frente.' }
Responder 'abierta' $msg $frente $proc.Id $consola.Titulo
