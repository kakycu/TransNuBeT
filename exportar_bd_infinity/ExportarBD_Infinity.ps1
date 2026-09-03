#=====================================================================
# ExportarBD_Infinity.ps1
# Exporta la BD local TransNuBeT_nomina a un archivo .sql listo para
# importar en InfinityFree con el nombre if0_42708110_transnubet_nomina.
#
# Uso:  .\ExportarBD_Infinity.ps1
#=====================================================================

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Clear-Host

# ----- Kit de marcos (caracteres) -----
function New-KitMarco {
    $c = @{
        TL = [string][char]0x2554  # ╔
        TR = [string][char]0x2557  # ╗
        BL = [string][char]0x255A  # ╚
        BR = [string][char]0x255D  # ╝
        V  = [string][char]0x2551  # ║
        H  = [string][char]0x2550  # ═
        TS = [string][char]0x2560  # ╠
        BS = [string][char]0x2563  # ╣
        UP = [string][char]0x2191  # ↑
        DN = [string][char]0x2193  # ↓
        MK = [string][char]0x25BA  # ►
        FB = [string][char]0x2588  # █ (relleno)
        SL = [string][char]0x258C  # ▌ (sombra lateral)
        SB = [string][char]0x2580  # ▀ (sombra inferior)
        SQ = [string][char]0x2598  # ▘ (esquina inferior sombra)
    }
    return $c
}
try { $codepage = [Console]::OutputEncoding.CodePage } catch { $codepage = 65001 }
$enWT = [bool]($env:WT_SESSION -or $env:TERM_PROGRAM)
if ($enWT -or $codepage -eq 65001) {
    try { [Console]::OutputEncoding = [System.Text.Encoding]::UTF8 } catch { }
    $script:CP = New-KitMarco
} elseif ($codepage -in @(437,850,852,855,857,858,860,861,862,863,864,865,866,869)) {
    $script:CP = New-KitMarco
} else {
    $script:CP = @{ TL='+'; TR='+'; BL='+'; BR='+'; V='|'; H='-'; TS='+'; BS='+'; UP='^'; DN='v'; MK='>'; FB='#'; SL='#'; SB='#' }
}

# Relleno del interior de la caja: fondo azul solido para los textos y para los
# caracteres del marco (bordes), rellenando los espacios en blanco con el
# caracter de relleno (FB = █) del mismo color.
$script:FondoRelleno = [ConsoleColor]::Blue        # fondo solido del interior
$script:ColorRelleno = [ConsoleColor]::Blue        # color del caracter de relleno (█)
$puedeCursor = $true
try {
    $null = $Host.UI.RawUI.KeyAvailable
    [Console]::SetCursorPosition(0, [Console]::CursorTop)
    [Console]::CursorVisible = $false
} catch { $puedeCursor = $false }

# ----- Rutas fijas -----
$PHP_EXE = 'C:\Users\kakyc\Desktop\TRANSNUBET\php\php.exe'
$USUARIO = 'root'
$DB_LOCAL = 'TransNuBeT_nomina'
$SALIDA_DEFAULT = Join-Path $PSScriptRoot 'if0_42708110_transnubet_nomina.sql'
$SALIDA_LOCAL   = Join-Path $PSScriptRoot 'TransNuBeT_nomina.sql'

$abrir      = $false
$minimizado = $false

# ----- Animaciones (usando Pintar-Interior para respetar bordes) -----
function Animacion-BarraProgreso([int]$seg = 0, [int]$fila) {
    if ($seg -le 0) { $seg = 2 }
    $pasos = 30
    $anchoBarra = 30
    for ($i = 0; $i -le $pasos; $i++) {
        $len = [int](($i / $pasos) * $anchoBarra)
        $txt = ("#") * $len
        $pct = [math]::Round(($i / $pasos) * 100)
        $linea = "  [$txt$(' ' * ($anchoBarra - $len))] $pct%"
        $texto = $linea.PadRight($W - 2).Substring(0, $W - 2)
        Pintar-Interior $fila $texto 'Green'
        Start-Sleep -Milliseconds (($seg * 1000) / $pasos)
    }
}

function Animacion-Puntos($mensaje, [int]$seg = 1, [int]$fila) {
    for ($i = 0; $i -lt 4; $i++) {
        $texto = " " + $mensaje + ("." * ($i + 1))
        $texto = $texto.PadRight($W - 2).Substring(0, $W - 2)
        Pintar-Interior $fila $texto 'Yellow'
        Start-Sleep -Milliseconds (($seg * 1000) / 4)
    }
}

function Setup-Done([int]$fila) {
    $frames = @("[OK]", "[OK ]", "[ OK]", "[ OK]")
    for ($c = 0; $c -lt 2; $c++) {
        for ($k = 0; $k -lt $frames.Count; $k++) {
            $texto = ("  Finalizando ... " + $frames[$k]).PadRight($W - 2).Substring(0, $W - 2)
            Pintar-Interior $fila $texto 'Green'
            Start-Sleep -Milliseconds 80
        }
    }
}

# ----- Estilo VUDU (con soporte para fondo solo donde se necesita) -----
$W = 62
$versionApp = "v2.1"
$tFecha = Get-Date -Format "dd-MM-yyyy hh:mm:ss tt"
$anioActual = (Get-Date).Year

# ----- Centrar ventana -----
$script:izq = 0
$script:top = 0
$colsVentana = 98
$rowsVentana = 30
try {
    if ([Console]::BufferWidth  -lt $colsVentana) { [Console]::BufferWidth  = $colsVentana }
    if ([Console]::BufferHeight -lt ($rowsVentana + 5)) { [Console]::BufferHeight = $rowsVentana + 5 }
    if ([Console]::WindowWidth  -ne $colsVentana) { [Console]::WindowWidth  = $colsVentana }
    if ([Console]::WindowHeight -ne $rowsVentana) { [Console]::WindowHeight = $rowsVentana }
} catch { }

try {
    Add-Type -AssemblyName System.Windows.Forms | Out-Null
    $scr = [System.Windows.Forms.Screen]::PrimaryScreen.WorkingArea
    if (-not ('Win32.Ventana' -as [type])) {
        Add-Type -Namespace Win32 -Name Ventana -MemberDefinition @'
[DllImport("user32.dll", SetLastError = true)]
public static extern bool MoveWindow(IntPtr hWnd, int X, int Y, int nWidth, int nHeight, bool bRepaint);
[DllImport("kernel32.dll", SetLastError = true)]
public static extern IntPtr GetConsoleWindow();
'@
    }
    $hwnd = [Win32.Ventana]::GetConsoleWindow()
    $anchoPx  = 1040
    $altoPx   = 700
    $x = $scr.X + [int](($scr.Width  - $anchoPx) / 2)
    $y = $scr.Y + [int](($scr.Height - $altoPx) / 2)
    if ($x -lt 0) { $x = 0 }; if ($y -lt 0) { $y = 0 }
    [Win32.Ventana]::MoveWindow($hwnd, $x, $y, $anchoPx, $altoPx, $true) | Out-Null
} catch { }

try { if ([Console]::WindowWidth -lt ($W + 8)) { $W = [math]::Max(40, [Console]::WindowWidth - 8) } } catch { }
if ($W -lt 40) { $W = 40 }
try { $script:izq = [math]::Max(0, [int](([Console]::WindowWidth - $W) / 2)) } catch { }
try { $script:top = [math]::Max(0, [int](([Console]::WindowHeight - 24) / 2)) } catch { }

# ----- Funciones de caja (marco en blanco, interior con fondo azul solido) -----
function Caja-Top {
    Reposicionar-Seq
    Write-Host (Indentar) -NoNewline
    Write-Host (($script:CP.TL) + ($script:CP.H * ($W - 2)) + ($script:CP.TR)) -ForegroundColor White -BackgroundColor $script:FondoRelleno
}
function Caja-Bottom {
    Write-Host (Indentar) -NoNewline
    Write-Host (($script:CP.BL) + ($script:CP.H * ($W - 2)) + ($script:CP.BR)) -ForegroundColor White -BackgroundColor $script:FondoRelleno
}
function Caja-Sep {
    Write-Host (Indentar) -NoNewline
    Write-Host (($script:CP.TS) + ($script:CP.H * ($W - 2)) + ($script:CP.BS)) -ForegroundColor White -BackgroundColor $script:FondoRelleno
}
function Caja-Linea {
    $relleno = $script:CP.FB * ($W - 2)
    Write-Host (Indentar) -NoNewline
    Write-Host ($script:CP.V) -NoNewline -ForegroundColor White -BackgroundColor $script:FondoRelleno
    Write-Host $relleno -NoNewline -ForegroundColor $script:ColorRelleno -BackgroundColor $script:FondoRelleno
    Write-Host ($script:CP.V) -ForegroundColor White -BackgroundColor $script:FondoRelleno
}
function Caja-Texto($texto, $color = 'White') {
    $t = " " + $texto
    if ($t.Length -gt ($W - 4)) { $t = $t.Substring(0, ($W - 4)) }
    $relleno = $script:CP.FB * (($W - 2) - $t.Length)
    Write-Host (Indentar) -NoNewline
    Write-Host ($script:CP.V) -NoNewline -ForegroundColor White -BackgroundColor $script:FondoRelleno
    Write-Host $t -NoNewline -ForegroundColor $color -BackgroundColor $script:FondoRelleno
    Write-Host $relleno -NoNewline -ForegroundColor $script:ColorRelleno -BackgroundColor $script:FondoRelleno
    Write-Host ($script:CP.V) -ForegroundColor White -BackgroundColor $script:FondoRelleno
}

function Indentar { return (" " * $script:izq) }

function Reposicionar-Seq {
    if ($puedeCursor) { try { [Console]::SetCursorPosition(0, $script:top) } catch { } }
}

# Pintar-DosColores: dos colores distintos en la misma fila, sobre el fondo solido.
function Pintar-DosColores([int]$row, $p1, $c1, $p2, $c2) {
    $full = " " + $p1 + $p2
    $len1 = (" " + $p1).Length
    if ($full.Length -gt ($W - 2)) {
        $full = $full.Substring(0, ($W - 2))
        if ($len1 -gt ($W - 2)) { $len1 = $W - 2 }
    }
    $resto = $script:CP.FB * (($W - 2) - $full.Length)
    if ($puedeCursor) {
        try { [Console]::SetCursorPosition((1 + $script:izq), ($row + $script:top)) } catch { }
        Write-Host $full.Substring(0, $len1) -NoNewline -ForegroundColor $c1 -BackgroundColor $script:FondoRelleno
        Write-Host $full.Substring($len1) -NoNewline -ForegroundColor $c2 -BackgroundColor $script:FondoRelleno
        Write-Host $resto -NoNewline -ForegroundColor $script:FondoRelleno -BackgroundColor $script:FondoRelleno
    } else {
        Write-Host (Indentar) -NoNewline
        Write-Host ($script:CP.V) -NoNewline -ForegroundColor White -BackgroundColor $script:FondoRelleno
        Write-Host $full.Substring(0, $len1) -NoNewline -ForegroundColor $c1 -BackgroundColor $script:FondoRelleno
        Write-Host $full.Substring($len1) -NoNewline -ForegroundColor $c2 -BackgroundColor $script:FondoRelleno
        Write-Host $resto -NoNewline -ForegroundColor $script:FondoRelleno -BackgroundColor $script:FondoRelleno
        Write-Host ($script:CP.V) -ForegroundColor White -BackgroundColor $script:FondoRelleno
    }
}

function Barra-Titulo($texto) {
    $t = " " + $texto
    if ($t.Length -gt ($W - 4)) { $t = $t.Substring(0, ($W - 4)) }
    $colorBorde = [ConsoleColor]::White
    $colorTexto = [ConsoleColor]::White
    if ($script:FondoRelleno -eq [ConsoleColor]::Yellow) { $colorTexto = [ConsoleColor]::Black }
    Write-Host (Indentar) -NoNewline
    Write-Host ($script:CP.V) -NoNewline -ForegroundColor $colorBorde -BackgroundColor $script:FondoRelleno
    Write-Host ($t.PadRight($W - 2)) -NoNewline -ForegroundColor $colorTexto -BackgroundColor $script:FondoRelleno
    Write-Host ($script:CP.V) -ForegroundColor $colorBorde -BackgroundColor $script:FondoRelleno
}

function Dibujar-Sombra([int]$filasCaja = -1) {
    if (-not $puedeCursor) { return }
    if ($filasCaja -lt 0) { $filasCaja = [Console]::CursorTop - $script:top }
    $x0 = $script:izq
    $y0 = $script:top
    try {
        # Lateral derecho: columna ▌ junto al marco, incluyendo la fila del ╝ (╝▌)
        for ($f = 1; $f -lt $filasCaja; $f++) {
            [Console]::SetCursorPosition(($x0 + $W), ($y0 + $f))
            Write-Host $script:CP.SL -NoNewline -ForegroundColor DarkGray
        }
        # Inferior: hilera de ▀ (cantidad-1) + ■ (cuadradito) donde convergen
        [Console]::SetCursorPosition(($x0 + 1), ($y0 + $filasCaja))
        Write-Host ($script:CP.SB * ($W - 1)) -NoNewline -ForegroundColor DarkGray
        [Console]::SetCursorPosition(($x0 + $W), ($y0 + $filasCaja))
        Write-Host $script:CP.SQ -NoNewline -ForegroundColor DarkGray
    } catch { }
    if ($puedeCursor) { try { [Console]::SetCursorPosition($x0, ($y0 + $filasCaja + 1)) } catch { } }
}

# Pintar-Interior: acepta un fondo opcional (para la animación y selección).
# El area despues del texto se rellena con FB (█) del MISMO color del fondo,
# de modo que la fila completa queda de un unico color solido (sin inversos).
function Pintar-Interior([int]$row, $texto, $color = 'White', $fondo = $null) {
    $t = " " + $texto
    if ($t.Length -gt ($W - 2)) { $t = $t.Substring(0, ($W - 2)) }
    if ($null -eq $fondo) { $fondo = $script:FondoRelleno }
    $relleno = $script:CP.FB * (($W - 2) - $t.Length)
    if ($puedeCursor) {
        try { [Console]::SetCursorPosition((1 + $script:izq), ($row + $script:top)) } catch { }
        Write-Host $t -NoNewline -ForegroundColor $color -BackgroundColor $fondo
        Write-Host $relleno -NoNewline -ForegroundColor $fondo -BackgroundColor $fondo
    } else {
        Write-Host (Indentar) -NoNewline
        Write-Host ($script:CP.V) -NoNewline -ForegroundColor White -BackgroundColor $fondo
        Write-Host $t -NoNewline -ForegroundColor $color -BackgroundColor $fondo
        Write-Host $relleno -NoNewline -ForegroundColor $fondo -BackgroundColor $fondo
        Write-Host ($script:CP.V) -ForegroundColor White -BackgroundColor $fondo
    }
}

# Tonos intensos (verdadero color ANSI) para los colores del menu sobre fondo azul.
# Cyan mas claro para contrastar con el fondo azul; el resto al maximo.
$script:Intensos = @{
    Cyan   = "150;240;255"
    Green  = "0;255;0"
    Yellow = "255;240;0"
    Red    = "255;70;70"
}

# Pintar una fila con el color intenso (ANSI 24-bit) sobre el fondo azul solido.
function Pintar-Intenso([int]$row, $texto, $color = 'Cyan', $fondo = $null) {
    if (-not $puedeCursor) { Pintar-Interior $row $texto $color $fondo; return }
    $t = " " + $texto
    if ($t.Length -gt ($W - 2)) { $t = $t.Substring(0, ($W - 2)) }
    if ($null -eq $fondo) { $fondo = $script:FondoRelleno }
    $relleno = $script:CP.FB * (($W - 2) - $t.Length)
    $rgb = $script:Intensos[$color]
    if (-not $rgb) { $rgb = "255;255;255" }
    $escC = [char]27
    try {
        [Console]::SetCursorPosition((1 + $script:izq), ($row + $script:top))
        Write-Host ($escC + "[38;2;" + $rgb + "m" + $t + $escC + "[0m") -NoNewline -BackgroundColor $fondo
        Write-Host $relleno -NoNewline -ForegroundColor $fondo -BackgroundColor $fondo
    } catch {
        Pintar-Interior $row $texto $color $fondo
    }
}

function Sonido-Nav   { if ($puedeCursor) { try { [Console]::Beep(480, 25) } catch {} } }
function Sonido-Enter { if ($puedeCursor) { try { [Console]::Beep(880, 60); Start-Sleep -Milliseconds 70; [Console]::Beep(1320, 90) } catch {} } }
function Sonido-Cerrar{ if ($puedeCursor) { try { [Console]::Beep(660, 60); Start-Sleep -Milliseconds 50; [Console]::Beep(330, 90) } catch {} } }
function Sonido-Error { if ($puedeCursor) { try { [Console]::Beep(220, 120) } catch {} } }

# ----- Menú simple (sin interactividad avanzada) -----
function Menu-Simple {
    $items = @(
        @{ Nombre = "Exportar la base de datos a .sql";            Accion = 1 },
        @{ Nombre = "Exportar y abrir el SQL resultante";          Accion = 2 },
        @{ Nombre = "Exportar, copiar ruta y abrir phpMyAdmin";    Accion = 3 },
        @{ Nombre = "Exportar Base de Datos Local";                Accion = 4 },
        @{ Nombre = "Salir";                                       Accion = 0 }
    )
    while ($true) {
        Clear-Host
        Caja-Top
        Barra-Titulo "  SISGESNOM :: EXPORTAR BD A INFINITYFREE      $versionApp"
        Caja-Texto "  TransNuBeT_nomina  ->  if0_42708110_transnubet_nomina" 'White'
        Caja-Sep
        foreach ($it in $items) {
            Caja-Texto ("   [" + $it.Accion + "]  " + $it.Nombre) 'White'
        }
        Caja-Sep
        Caja-Texto "  Elija una opcion (0-4):" 'Cyan'
        Caja-Bottom
        Dibujar-Sombra 11
		
        # Copyright 2 líneas abajo
$copyText = "Copyright (c) 2020 - $($anioActual). Unicornio Software°"
        $padCopy = [math]::Max(0, [int](($W - $copyText.Length) / 2))
        Write-Host ""
        Write-Host ((Indentar) + (" " * $padCopy) + $copyText) -ForegroundColor DarkGray

		
        $k = Read-Host "  [0-4]"
        switch ($k) {
            '1' { return 1 }
            '2' { return 2 }
            '3' { return 3 }
            '4' { return 4 }
            '0' { return 0 }
            default { Write-Host "  Opcion no valida." -ForegroundColor Red }
        }
    }
}

# ----- Menú VUDU interactivo (con inversión en título y selección) -----
function Menu-Interactivo {
    $items = @(
        @{ Nombre = "Exportar la base de datos a .sql";          Desc = "Genera if0_42708110_transnubet_nomina.sql";      Reale = 'Cyan';    Accion = 1 },
        @{ Nombre = "Exportar y abrir el SQL resultante";        Desc = "Genera el .sql y lo abre con el Bloc de notas";   Reale = 'Green';   Accion = 2 },
        @{ Nombre = "Exportar, copiar ruta y abrir phpMyAdmin";  Desc = "Abre la pestana Importar y copia la ruta";        Reale = 'Yellow';  Accion = 3 },
        @{ Nombre = "Exportar Base de Datos Local";              Desc = "Genera TransNuBeT_nomina.sql (dump local)";       Reale = 'Magenta'; Accion = 4 },
        @{ Nombre = "Salir";                                     Desc = "Cierra el asistente";                              Reale = 'Red';     Accion = 0 }
    )
    $sel = 0
    $enter = 13; $esc = 27; $arriba = 38; $abajo = 40

    $filaItem    = 7
    $salto       = 3
    $filaAyuda   = $filaItem + ($items.Count * $salto) + 1

    function Fila-Nombre([int]$i)  { return $filaItem + ($i * $salto) }
    function Fila-Desc([int]$i)    { return $filaItem + ($i * $salto) + 1 }

    function Dibujar-Marco {
        Clear-Host
        $filasSep = @(4, 6, ($filaAyuda - 1))
        $filaMarcoB = $filaAyuda + 1
        for ($f = 0; $f -le $filaMarcoB; $f++) {
            if ($f -eq 0) { Caja-Top }
            elseif ($f -eq $filaMarcoB) { Caja-Bottom }
            elseif ($f -in $filasSep) { Caja-Sep }
            else { Caja-Linea }
        }
        Pintar-Interior 1 ("  SISGESNOM :: EXPORTAR BD A INFINITYFREE      " + $versionApp) 'White'
        Pintar-Interior 2 "  TransNuBeT_nomina   ->   if0_42708110_transnubet_nomina" 'White'
        Pintar-DosColores 3 "  Fecha:" 'White' ("  " + $tFecha) 'Black'
        $infoSql = Get-Item $SALIDA_DEFAULT -ErrorAction SilentlyContinue
        if ($infoSql) {
            $tam = [math]::Round($infoSql.Length / 1MB, 2)
            $fech = $infoSql.LastWriteTime.ToString("yyyy-MM-dd HH:mm")
            Pintar-DosColores 5 "  Ultima exportacion:" 'Yellow' (" $tam MB  ($fech)") 'Black'
        } else {
            Pintar-DosColores 5 "  Ultima exportacion:" 'Yellow' "  (ninguna)" 'Black'
        }
        if ($puedeCursor) {
            Pintar-Interior $filaAyuda ("  " + $script:CP.UP + $script:CP.DN + " Navegar   ENTER Seleccionar   Q/Esc Salir") 'White'
        }
    }

    function Pintar-Opcion([int]$i, [bool]$activa) {
        $it = $items[$i]
        if ($activa) {
            Pintar-Interior (Fila-Nombre $i) ("     [" + $it.Accion + "]  " + $it.Nombre) 'Black' $it.Reale
            Pintar-Interior (Fila-Desc $i)   ("      " + $it.Desc) 'Black' $it.Reale
        } elseif ($it.Accion -eq 0) {
            # Salir en amarillo
            Pintar-Interior (Fila-Nombre $i) ("     [" + $it.Accion + "]  " + $it.Nombre) 'Yellow'
            Pintar-Interior (Fila-Desc $i)   ("      " + $it.Desc) 'Yellow'
        } else {
            # Demas opciones: texto en negro sobre fondo azul
            Pintar-Interior (Fila-Nombre $i) ("     [" + $it.Accion + "]  " + $it.Nombre) 'Black'
            Pintar-Interior (Fila-Desc $i)   ("      " + $it.Desc) 'Black'
        }
    }

    Dibujar-Marco
    Dibujar-Sombra (($filaAyuda + 2))

    # >>> DIBUJAR COPYRIGHT CENTRADO DEBAJO DEL MARCO INTERACTIVO <<<
    $copyText = "Copyright (c) 2020 - $($anioActual). Unicornio Software°"
    $padCopy  = [math]::Max(0, [int](($W - $copyText.Length) / 2))
    try {
        # Sin salto de linea (-NoNewline) para no forzar un scroll en la ultima
        # fila visible de la ventana, que desplazaba el marco y duplicaba el titulo.
        [Console]::SetCursorPosition(($script:izq + $padCopy), ($script:top + $filaAyuda + 3))
        Write-Host $copyText -NoNewline -ForegroundColor DarkGray
    } catch {
        Write-Host ""
        Write-Host ((Indentar) + (" " * $padCopy) + $copyText) -ForegroundColor DarkGray
    }

    for ($i = 0; $i -lt $items.Count; $i++) { Pintar-Opcion $i ($i -eq $sel) }

    # Dibuja el marcador ► en la fila de la seleccion actual, sincronizado con la
    # animacion (alterna ►/espacio segun el visor).
    function Pintar-Marcador([int]$visorMarcador) {
        if (-not $puedeCursor) { return }
        try {
            [Console]::SetCursorPosition((3 + $script:izq), ((Fila-Nombre $sel) + $script:top))
            if ([math]::Floor($visorMarcador / 2) % 2 -eq 0) {
                Write-Host $script:CP.MK -NoNewline -ForegroundColor Black -BackgroundColor ($items[$sel].Reale)
            } else {
                Write-Host " " -NoNewline -ForegroundColor Black -BackgroundColor ($items[$sel].Reale)
            }
        } catch { }
    }

    $visor = 0
    $sw = [System.Diagnostics.Stopwatch]::StartNew()
    $ultimoTick = 0
    while ($true) {
        # --- Animacion a ritmo fijo (no se detiene al navegar) ---
        $ms = $sw.ElapsedMilliseconds
        if (($ms - $ultimoTick) -ge 200) {
            $ultimoTick = $ms
            $visor++
            if ($puedeCursor) {
                $titulo = "  SISGESNOM :: EXPORTAR BD A INFINITYFREE      " + $versionApp
                try {
                    # Repintar la fila 1 del marco: bordes ║ siempre normales, y solo
                    # el interior (izq+1 .. izq+W-2) se invierte (azul <-> blanco).
                    $AnchoInterior = $W - 2
                    $interior = " " + $titulo
                    if ($interior.Length -gt $AnchoInterior) { $interior = $interior.Substring(0, $AnchoInterior) }
                    $relleno = ($script:CP.FB * ($AnchoInterior - $interior.Length))
                    [Console]::SetCursorPosition($script:izq, (1 + $script:top))
                    Write-Host $script:CP.V -NoNewline -ForegroundColor White -BackgroundColor Blue
                    if ([math]::Floor($visor / 2) % 2 -eq 0) {
                        # Texto blanco sobre fondo azul; el relleno ██ solidifica el
                        # color de fondo del texto (azul).
                        Write-Host $interior -NoNewline -ForegroundColor White -BackgroundColor Blue
                        Write-Host $relleno -NoNewline -ForegroundColor Blue -BackgroundColor Blue
                    } else {
                        # Texto azul sobre fondo blanco; el relleno ██ solidifica el
                        # color de fondo del texto (blanco).
                        Write-Host $interior -NoNewline -ForegroundColor Blue -BackgroundColor White
                        Write-Host $relleno -NoNewline -ForegroundColor White -BackgroundColor White
                    }
                    Write-Host $script:CP.V -NoNewline -ForegroundColor White -BackgroundColor Blue
                } catch { }
                Pintar-Marcador $visor
            }
        }

        # --- Procesar todas las teclas pendientes ---
        while ($Host.UI.RawUI.KeyAvailable) {
            $key = $Host.UI.RawUI.ReadKey('NoEcho,IncludeKeyDown')
            switch ($key.VirtualKeyCode) {
                $arriba {
                    Sonido-Nav
                    Pintar-Opcion $sel $false
                    $sel--
                    if ($sel -lt 0) { $sel = $items.Count - 1 }
                    Pintar-Opcion $sel $true
                    Pintar-Marcador $visor
                }
                $abajo {
                    Sonido-Nav
                    Pintar-Opcion $sel $false
                    $sel++
                    if ($sel -ge $items.Count) { $sel = 0 }
                    Pintar-Opcion $sel $true
                    Pintar-Marcador $visor
                }
                $enter {
                    Sonido-Enter
                    $itSel = $items[$sel]
                    MostrarNotificacion -Title "SISGESNOM" -Text ("Opcion seleccionada: " + $itSel.Nombre) -Level "info" -Timeout 4000
                    return $itSel.Accion
                }
                $esc {
                    Sonido-Cerrar
                    return 0
                }
                default {
                    $k = [string]$key.Character
                    if ($k -eq 'q' -or $k -eq 'Q') { Sonido-Cerrar; return 0 }
                    if ($k -eq '1') { Sonido-Enter; return 1 }
                    if ($k -eq '2') { Sonido-Enter; return 2 }
                    if ($k -eq '3') { Sonido-Enter; return 3 }
                    if ($k -eq '4') { Sonido-Enter; return 4 }
                    if ($k -eq '0') { Sonido-Enter; return 0 }
                }
            }
        }
        Start-Sleep -Milliseconds 10
    }
}
# =====================================================================
# ----- Ventana Emergente MS-DOS Superpuesta (Pedir-Credenciales) -----
# =====================================================================
function Pedir-Credenciales {
    if (-not $puedeCursor) {
        # Respaldo simple para consolas que no admiten cursor
        Clear-Host
        Write-Host "--- CREDENCIALES MYSQL LOCAL ---" -ForegroundColor Cyan
        $u = Read-Host "Usuario [root]"
        if ([string]::IsNullOrWhiteSpace($u)) { $u = "root" }
        $p = Read-Host "Contrasena" -AsSecureString
        $bstr = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($p)
        $pTxt = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto($bstr)
        return [PSCustomObject]@{ Usuario = $u; Password = $pTxt }
    }

    $W_Dlg = 50
    $H_Dlg = 12
    $xDlg  = [math]::Max(0, [int](([Console]::WindowWidth  - $W_Dlg) / 2))
    $yDlg  = [math]::Max(0, [int](([Console]::WindowHeight - $H_Dlg) / 2))

    # Guardar estado y dibujar caja modal superpuesta
    # Fondo del diálogo: DarkGray (distinto al azul del menú), borde █ negro.
    $script:dlgFondoOld = $script:FondoRelleno
    $script:dlgColorOld = $script:ColorRelleno
    $script:FondoRelleno = [ConsoleColor]::DarkGray
    $script:ColorRelleno = [ConsoleColor]::DarkGray
    function Pintar-FilaDlg([int]$relY, [string]$contenido, [string]$color = 'Cyan', $fondo = $null) {
        try {
            [Console]::SetCursorPosition($xDlg, ($yDlg + $relY))
            if ($null -ne $fondo) {
                Write-Host $contenido -NoNewline -ForegroundColor $color -BackgroundColor $fondo
            } else {
                Write-Host $contenido -NoNewline -ForegroundColor $color
            }
        } catch {}
    }

    function Sombra-Dialogo {
        try {
            for ($f = 1; $f -lt $H_Dlg; $f++) {
                [Console]::SetCursorPosition(($xDlg + $W_Dlg), ($yDlg + $f))
                Write-Host $script:CP.SL -NoNewline -ForegroundColor DarkGray
            }
            [Console]::SetCursorPosition(($xDlg + 1), ($yDlg + $H_Dlg))
            Write-Host ($script:CP.SB * ($W_Dlg - 1)) -NoNewline -ForegroundColor DarkGray
            [Console]::SetCursorPosition(($xDlg + $W_Dlg), ($yDlg + $H_Dlg))
            Write-Host $script:CP.SQ -NoNewline -ForegroundColor DarkGray
        } catch {}
    }

    # Dibujar marco exterior del diálogo con borde solido de █ (acento negro)
    $borde = $script:CP.FB  # █
    $bordeCol = [ConsoleColor]::Black
    # Superior: linea completa de █
    Pintar-FilaDlg 0  ($borde * $W_Dlg) $bordeCol
    # Fila del titulo con borde █ a los lados
    try {
        [Console]::SetCursorPosition(($xDlg + 1), ($yDlg + 1))
        Write-Host (" CREDENCIALES MYSQL LOCAL".PadRight($W_Dlg - 2)) -NoNewline -ForegroundColor White -BackgroundColor $script:FondoRelleno
        [Console]::SetCursorPosition($xDlg, ($yDlg + 1))
        Write-Host $borde -NoNewline -ForegroundColor $bordeCol
        [Console]::SetCursorPosition(($xDlg + $W_Dlg - 1), ($yDlg + 1))
        Write-Host $borde -ForegroundColor $bordeCol
    } catch {}

    # Separador solido
    Pintar-FilaDlg 2  ($borde * $W_Dlg) $bordeCol
    for ($f = 3; $f -lt ($H_Dlg - 1); $f++) {
        Pintar-FilaDlg $f $borde $bordeCol
        try {
            [Console]::SetCursorPosition(($xDlg + 1), ($yDlg + $f))
            Write-Host ($script:CP.FB * ($W_Dlg - 2)) -NoNewline -ForegroundColor $script:ColorRelleno -BackgroundColor $script:FondoRelleno
            [Console]::SetCursorPosition(($xDlg + $W_Dlg - 1), ($yDlg + $f))
            Write-Host $borde -NoNewline -ForegroundColor $bordeCol
        } catch {}
    }
    # Inferior: linea completa de █
    Pintar-FilaDlg ($H_Dlg - 1) ($borde * $W_Dlg) $bordeCol
    Sombra-Dialogo

    # Sub-datos informativos en el diálogo
    $txtHost = "  BD: TransNuBeT_nomina  |  Host: localhost"
    try {
        [Console]::SetCursorPosition(($xDlg + 1), ($yDlg + 3))
        Write-Host ($txtHost.PadRight($W_Dlg - 2)) -NoNewline -ForegroundColor White -BackgroundColor $script:FondoRelleno
    } catch {}

    # Variables de estado del diálogo
    $usrText  = "root"
    $passText = ""
    $foco     = 1  # 0: Usuario, 1: Password (foco por defecto), 2: [Aceptar], 3: [Cancelar]
    $maxInput = 20

    function Actualizar-Campos {
        # Campo Usuario (Fila 4) y Contraseña (Fila 6) sobre fondo solido
        try {
            [Console]::SetCursorPosition(($xDlg + 1), ($yDlg + 4))
            Write-Host (" ".PadRight($W_Dlg - 2)) -NoNewline -BackgroundColor $script:FondoRelleno
            [Console]::SetCursorPosition(($xDlg + 1), ($yDlg + 6))
            Write-Host (" ".PadRight($W_Dlg - 2)) -NoNewline -BackgroundColor $script:FondoRelleno
        } catch {}

        # Campo Usuario (Fila 5)
        $uPadded = (" " + $usrText).PadRight($maxInput)
        try {
            [Console]::SetCursorPosition(($xDlg + 3), ($yDlg + 4))
            Write-Host "Usuario   : " -NoNewline -ForegroundColor White -BackgroundColor $script:FondoRelleno
            if ($foco -eq 0) {
                Write-Host "[$uPadded]" -NoNewline -ForegroundColor Black -BackgroundColor Cyan
            } else {
                Write-Host "[$uPadded]" -NoNewline -ForegroundColor Cyan -BackgroundColor DarkGray
            }
        } catch {}

        # Campo Contraseña (Fila 7)
        $pMasked = (" " + ("*" * $passText.Length)).PadRight($maxInput)
        try {
            [Console]::SetCursorPosition(($xDlg + 3), ($yDlg + 6))
            Write-Host "Contraseña: " -NoNewline -ForegroundColor White -BackgroundColor $script:FondoRelleno
            if ($foco -eq 1) {
                Write-Host "[$pMasked]" -NoNewline -ForegroundColor Black -BackgroundColor Cyan
            } else {
                Write-Host "[$pMasked]" -NoNewline -ForegroundColor Cyan -BackgroundColor DarkGray
            }
        } catch {}

        # Botones (Fila 8)
        try {
            [Console]::SetCursorPosition(($xDlg + 9), ($yDlg + 8))
            if ($foco -eq 2) {
                Write-Host " [ Aceptar ] " -NoNewline -ForegroundColor Black -BackgroundColor Green
            } else {
                Write-Host " [ Aceptar ] " -NoNewline -ForegroundColor Green -BackgroundColor $script:FondoRelleno
            }

            Write-Host "    " -NoNewline -BackgroundColor $script:FondoRelleno

            if ($foco -eq 3) {
                Write-Host " [ Cancelar ] " -NoNewline -ForegroundColor Black -BackgroundColor Red
            } else {
                Write-Host " [ Cancelar ] " -NoNewline -ForegroundColor Red -BackgroundColor $script:FondoRelleno
            }
        } catch {}

        # Posicionar cursor en el campo activo para feedback visual
        try {
            if ($foco -eq 0) {
                [Console]::SetCursorPosition(($xDlg + 16 + $usrText.Length), ($yDlg + 4))
                [Console]::CursorVisible = $true
            } elseif ($foco -eq 1) {
                [Console]::SetCursorPosition(($xDlg + 16 + $passText.Length), ($yDlg + 6))
                [Console]::CursorVisible = $true
            } else {
                [Console]::CursorVisible = $false
            }
        } catch {}
    }

    Actualizar-Campos

    # Bucle de interacción del diálogo MS-DOS
    while ($true) {
        $key = [Console]::ReadKey($true)
        switch ($key.Key) {
            'Tab' {
                Sonido-Nav
                if ($key.Modifiers -band [System.ConsoleModifiers]::Shift) {
                    $foco = ($foco - 1 + 4) % 4
                } else {
                    $foco = ($foco + 1) % 4
                }
                Actualizar-Campos
            }
            'UpArrow' {
                Sonido-Nav
                $foco = ($foco - 1 + 4) % 4
                Actualizar-Campos
            }
            'DownArrow' {
                Sonido-Nav
                $foco = ($foco + 1) % 4
                Actualizar-Campos
            }
            'LeftArrow' {
                if ($foco -eq 3) { Sonido-Nav; $foco = 2; Actualizar-Campos }
            }
            'RightArrow' {
                if ($foco -eq 2) { Sonido-Nav; $foco = 3; Actualizar-Campos }
            }
            'Escape' {
                [Console]::CursorVisible = $false
                Sonido-Cerrar
                $script:FondoRelleno = $script:dlgFondoOld
                $script:ColorRelleno = $script:dlgColorOld
                return $null
            }
            'Enter' {
                if ($foco -eq 3) {
                    [Console]::CursorVisible = $false
                    Sonido-Cerrar
                    $script:FondoRelleno = $script:dlgFondoOld
                    $script:ColorRelleno = $script:dlgColorOld
                    return $null
                } elseif ($foco -eq 0) {
                    Sonido-Nav
                    $foco = 1
                    Actualizar-Campos
                } else {
                    [Console]::CursorVisible = $false
                    Sonido-Enter
                    $script:FondoRelleno = $script:dlgFondoOld
                    $script:ColorRelleno = $script:dlgColorOld
                    return [PSCustomObject]@{ Usuario = $usrText; Password = $passText }
                }
            }
            'Backspace' {
                if ($foco -eq 0 -and $usrText.Length -gt 0) {
                    $usrText = $usrText.Substring(0, $usrText.Length - 1)
                    Actualizar-Campos
                } elseif ($foco -eq 1 -and $passText.Length -gt 0) {
                    $passText = $passText.Substring(0, $passText.Length - 1)
                    Actualizar-Campos
                }
            }
            default {
                $c = $key.KeyChar
                if (-not [char]::IsControl($c)) {
                    if ($foco -eq 0 -and $usrText.Length -lt ($maxInput - 2)) {
                        $usrText += $c
                        Actualizar-Campos
                    } elseif ($foco -eq 1 -and $passText.Length -lt ($maxInput - 2)) {
                        $passText += $c
                        Actualizar-Campos
                    }
                }
            }
        }
    }
}

# ----- Salir (cierra la ventana directamente) -----
function Cerrar-Asistente {
    Clear-Host
    exit
}

# ----- Notificacion de globo en el area de notificaciones (Windows) -----
function MostrarNotificacion {
    param (
        [string]$Title = "Notificación",
        [string]$Text = "Mensaje predeterminado",
        [ValidateSet("info","warning","error","success")]
        [string]$Level = "info",
        [int]$Timeout = 5000,
        [string]$IconPath = "",
        [scriptblock]$OnClick = $null
    )

    try {
        Add-Type -AssemblyName System.Windows.Forms | Out-Null
        Add-Type -AssemblyName System.Drawing | Out-Null
    } catch { return }

    $notify = New-Object System.Windows.Forms.NotifyIcon

    # Configurar icono
    if ($IconPath -and (Test-Path $IconPath)) {
        $notify.Icon = [System.Drawing.Icon]::ExtractAssociatedIcon($IconPath)
    } else {
        switch ($Level) {
            "info"    { $notify.Icon = [System.Drawing.SystemIcons]::Information }
            "warning" { $notify.Icon = [System.Drawing.SystemIcons]::Warning }
            "error"   { $notify.Icon = [System.Drawing.SystemIcons]::Error }
            "success" { $notify.Icon = [System.Drawing.SystemIcons]::Application }
        }
    }

    # Configurar balloon tip
    $notify.BalloonTipTitle = $Title
    $notify.BalloonTipText = $Text
    $notify.BalloonTipIcon = switch ($Level) {
        "info"    { [System.Windows.Forms.ToolTipIcon]::Info }
        "warning" { [System.Windows.Forms.ToolTipIcon]::Warning }
        "error"   { [System.Windows.Forms.ToolTipIcon]::Error }
        default   { [System.Windows.Forms.ToolTipIcon]::None }
    }

    $notify.Visible = $true

    # Acción al hacer clic
    if ($OnClick) { $notify.Add_Click($OnClick) }

    # Mostrar notificación
    $notify.ShowBalloonTip($Timeout)

    # Limpieza después del tiempo especificado
    Start-Job -ScriptBlock {
        param($timeout, $notify)
        Start-Sleep -Milliseconds ($timeout + 500)
        $notify.Dispose()
    } -ArgumentList $Timeout, $notify | Out-Null
}

# ----- Bucle principal -----
MostrarNotificacion -Title "SISGESNOM :: Exportar BD" -Text "Asistente de exportacion iniciado. Elija una opcion en la consola." -Level "info" -Timeout 4000
$salir = $false
while (-not $salir) {
    if ($puedeCursor) { $opcion = Menu-Interactivo } else { $opcion = Menu-Simple }
    switch ($opcion) {
        1 { $abrir = $false; $minimizado = $false; $esLocal = $false }
        2 { $abrir = $true;  $minimizado = $false; $esLocal = $false }
        3 { $abrir = $true;  $minimizado = $true;  $esLocal = $false }
        4 { $abrir = $false; $minimizado = $false; $esLocal = $true }
        0 { Cerrar-Asistente }
        default { continue }
    }
    # Si llegamos aquí, es porque se eligió exportar (1,2,3)
    $creds = Pedir-Credenciales
    if (-not $creds) {
        Write-Host ""
        Write-Host "  Operacion cancelada." -ForegroundColor Red
        Start-Sleep -Milliseconds 600
        continue
    }
    $USUARIO = $creds.Usuario
    $passText = $creds.Password

    if ([string]::IsNullOrWhiteSpace($USUARIO)) {
        Write-Host ""
        Write-Host "  FALTA BD_USER: el usuario esta vacio." -ForegroundColor Red
        Read-Host "  Presione Enter para volver al menu"
        continue
    }

    if ($esLocal) {
        # Nombre con marca de tiempo: TransNuBeT_nomina-31-08-2026--12-35-54-AM.sql
        $marca = Get-Date -Format "dd-MM-yyyy--hh-mm-ss-tt"
        $salida = Join-Path $PSScriptRoot ("TransNuBeT_nomina-" + $marca + ".sql")
    } else {
        $salida = $SALIDA_DEFAULT
    }

# ----- Script PHP (sin cambios) -----
$phpScript = @'
<?php
set_time_limit(0);
ini_set('error_reporting', E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('memory_limit', '512M');

$db   = getenv('DB_NAME');
$user = getenv('BD_USER');
$pass = getenv('BD_PASS');
$out  = getenv('BD_OUT');
$dest = getenv('BD_DEST');
$create = getenv('BD_CREATE');

if ($pass === false) { fwrite(STDERR, "FALTA BD_PASS\n"); exit(1); }
if ($out  === false) { fwrite(STDERR, "FALTA BD_OUT\n");  exit(1); }
if ($dest === false) { $dest = $db; }

setlocale(LC_ALL, "es_ES");
setlocale(LC_TIME, "spanish");
date_default_timezone_set('America/New_York');

try {
    $pdo = new PDO("mysql:host=localhost;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 30,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "NO SE PUDO CONECTAR: " . $e->getMessage() . "\n");
    exit(1);
}

$serverVersion = $pdo->query('SELECT VERSION()')->fetchColumn();
$phpVersion    = phpversion();

$fechaGeneracion = date('d-m-Y');
$horaGeneracion  = date("h:i:s A", strtotime('-1 hour'));

$content = "-- phpMyAdmin SQL Dump\n";
$content .= "-- https://www.phpmyadmin.net/\n";
$content .= "--\n";
$content .= "-- Servidor: localhost\n";
$content .= "-- Versión del servidor: " . $serverVersion . "\n";
$content .= "-- Versión de PHP: " . $phpVersion . "\n\n";

$content .= "-- Exportaciones Nóminas TransNuBeT --\n";
$content .= "-- Salva SQL de las Bases de Datos del Sistema de Nóminas TransNuBeT\n";
$content .= "-- Webmaster: Franklin Ramos Lamadrid (kaky°)®\n";
$content .= "-- Email: kakycu@gmail.com\n";
$content .= "-- Copyright © 2025 - " . date('Y') . ".\n";
$content .= "-- ------------------------------------------------------------------------------\n";
$content .= "-- phpMyAdmin Volcado de Datos SQL.\n";
$content .= "-- ------------------------------------------------------------------------------\n";
$content .= "-- Destino InfinityFree: $dest\n";
$content .= "-- ------------------------------------------------------------------------------\n";
$content .= "-- Nombre del Servidor: localhost\n";
$content .= "-- Dirección del Servidor: 127.0.0.1\n";
$content .= "-- Tiempo de generación: " . $fechaGeneracion . " a las " . $horaGeneracion . "\n\n";

$content .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
$content .= "START TRANSACTION;\n";
$content .= "SET time_zone = \"+00:00\";\n\n";
if ($create === '1') {
    $content .= "DROP DATABASE IF EXISTS `" . strtolower($db) . "`;\n";
    $content .= "CREATE DATABASE `" . strtolower($db) . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n";
    $content .= "USE `" . strtolower($db) . "`;\n\n";
    $content .= "--\n-- Base de datos: `" . strtolower($db) . "`\n--\n\n";
}

$content .= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
$content .= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
$content .= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
$content .= "/*!40101 SET NAMES utf8mb4 */;\n\n";

$tablas = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN, 0);
if (empty($tablas)) {
    file_put_contents($out, $content . "-- (No hay tablas)\n");
    echo "OK - no hay tablas\n";
    exit(0);
}

$lista = array_map(function ($t) { return "`" . $t . "`"; }, $tablas);
$content .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
$content .= "DROP TABLE " . implode(', ', $lista) . ";\n\n";
$content .= "SET FOREIGN_KEY_CHECKS = 1;\n\n";

$columnTypes = [];
$virtualColumns = [];
$indicesByTable = [];
$constraintsByTable = [];
$autoIncrementByTable = [];

foreach ($tablas as $table) {
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
    $columnTypes[$table] = [];
    $virtualColumns[$table] = [];
    $hasAutoIncrement = false;
    $autoIncrementColumn = null;
    $autoIncrementType = null;

    while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columnTypes[$table][$col['Field']] = $col['Type'];
        if (stripos($col['Extra'], 'auto_increment') !== false) {
            $hasAutoIncrement = true;
            $autoIncrementColumn = $col['Field'];
            $autoIncrementType = $col['Type'];
        }
        if (stripos($col['Extra'], 'generated') !== false ||
            stripos($col['Extra'], 'virtual') !== false ||
            stripos($col['Extra'], 'stored') !== false) {
            $virtualColumns[$table][] = $col['Field'];
        }
    }

    $content .= "-- --------------------------------------------------------\n\n";
    $content .= "--\n-- Estructura de tabla para la tabla `$table`\n--\n\n";

    $row = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
    $rawSql = $row[1];

    $currentAutoIncrement = null;
    if (preg_match('/AUTO_INCREMENT=(\d+)/', $rawSql, $matches)) {
        $currentAutoIncrement = $matches[1];
    }
    if ($hasAutoIncrement) {
        $autoIncrementByTable[$table] = [
            'field' => $autoIncrementColumn,
            'type'  => $autoIncrementType,
            'val'   => $currentAutoIncrement !== null ? $currentAutoIncrement : 1
        ];
    }

    $lines = explode("\n", $rawSql);
    $cleanColumns = [];
    $tableIndices = [];
    $tableConstraints = [];
    $engineInfo = "";

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (preg_match('/^\) ENGINE=/', $trimmed)) {
            $engineLine = preg_replace('/ AUTO_INCREMENT=\d+/', '', $trimmed);
            $engineInfo = ltrim($engineLine, ')');
            continue;
        }
        if (preg_match('/^(PRIMARY KEY|UNIQUE KEY|KEY)/i', $trimmed)) {
            $tableIndices[] = rtrim($trimmed, ',');
            continue;
        }
        if (preg_match('/^CONSTRAINT/i', $trimmed)) {
            $tableConstraints[] = rtrim($trimmed, ',');
            continue;
        }
        if (preg_match('/^`/', $trimmed)) {
            $colDef = preg_replace('/ AUTO_INCREMENT/i', '', $trimmed);
            $cleanColumns[] = rtrim($colDef, ',');
        }
    }

    $content .= "CREATE TABLE `$table` (\n  " . implode(",\n  ", array_filter($cleanColumns)) . "\n)" . $engineInfo . ";\n\n";

    if (!empty($tableIndices))  { $indicesByTable[$table] = $tableIndices; }
    if (!empty($tableConstraints)) { $constraintsByTable[$table] = $tableConstraints; }

    $rows = $pdo->query("SELECT * FROM `$table`");
    $allRows = $rows->fetchAll(PDO::FETCH_ASSOC);

    if (count($allRows) > 0) {
        $content .= "--\n-- Volcado de datos para la tabla `$table`\n--\n\n";

        $allColumnNames = array_keys($allRows[0]);
        $virtualForThisTable = $virtualColumns[$table] ?? [];
        $columnNamesForInsert = array_diff($allColumnNames, $virtualForThisTable);

        if (!empty($columnNamesForInsert)) {
            $columnsList = "`" . implode("`, `", $columnNamesForInsert) . "`";
            $content .= "INSERT INTO `$table` ($columnsList) VALUES\n";

            $dataRows = [];
            foreach ($allRows as $r) {
                $vals = [];
                foreach ($columnNamesForInsert as $colName) {
                    $v = $r[$colName];
                    if ($v === null) {
                        $vals[] = "NULL";
                    } else {
                        $colType = strtolower(trim($columnTypes[$table][$colName] ?? ''));
                        $isStrictInteger = preg_match('/^(tinyint|smallint|mediumint|int|bigint)(\(|$)/', $colType);
                        $isDecimalType = preg_match('/^(decimal|numeric|float|double|real)(\(|$)/', $colType);
                        if ($isStrictInteger && !$isDecimalType) {
                            $vals[] = $v;
                        } else {
                            $vals[] = $pdo->quote((string)$v);
                        }
                    }
                }
                $dataRows[] = "(" . implode(", ", $vals) . ")";
            }
            $content .= implode(",\n", $dataRows) . ";\n\n";
        }
    }
}

if (!empty($indicesByTable)) {
    $content .= "--\n-- Índices para tablas volcadas\n--\n\n";
    foreach ($indicesByTable as $tableName => $indices) {
        $content .= "--\n-- Índices de la tabla `$tableName`\n--\n";
        $content .= "ALTER TABLE `$tableName`\n  ADD " . implode(",\n  ADD ", $indices) . ";\n\n";
    }
}

if (!empty($autoIncrementByTable)) {
    $content .= "--\n-- AUTO_INCREMENT de las tablas volcadas\n--\n\n";
    foreach ($autoIncrementByTable as $tableName => $info) {
        $content .= "--\n-- AUTO_INCREMENT de la tabla `$tableName`\n--\n";
        if ($info['field'] && $info['type']) {
            $content .= "ALTER TABLE `$tableName`\n";
            $content .= "  MODIFY `{$info['field']}` {$info['type']} NOT NULL AUTO_INCREMENT, AUTO_INCREMENT={$info['val']};\n\n";
        } else {
            $content .= "ALTER TABLE `$tableName` AUTO_INCREMENT = {$info['val']};\n\n";
        }
    }
}

if (!empty($constraintsByTable)) {
    $content .= "--\n-- Restricciones para tablas volcadas\n--\n\n";
    foreach ($constraintsByTable as $tableName => $constraints) {
        $content .= "--\n-- Filtros para la tabla `$tableName`\n--\n";
        $content .= "ALTER TABLE `$tableName`\n  ADD " . implode(",\n  ADD ", $constraints) . ";\n\n";
    }
}

$content .= "COMMIT;\n\n";
$content .= "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n";
$content .= "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n";
$content .= "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n";

if (file_put_contents($out, $content) === false) {
    fwrite(STDERR, "No se pudo escribir el archivo SQL\n");
    exit(1);
}
echo "OK - Exportado en: $out\n";
echo "Tablas: " . count($tablas) . "\n";
'@

    # ----- Generar PHP temporal -----
    $phpFile = Join-Path $env:TEMP ("ExportarBD_" + [guid]::NewGuid().ToString('N') + ".php")
    $utf8 = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($phpFile, $phpScript, $utf8)

    try {
        Clear-Host
        # --- Construir la caja de exportación (marco completo, luego animaciones) ---
        Caja-Top
        Barra-Titulo "  SISGESNOM :: EXPORTAR BD A INFINITYFREE      $versionApp"
        Caja-Sep
        Caja-Texto "  INICIANDO EXPORTACION..." 'Yellow'
        Caja-Linea
        Caja-Linea
        Caja-Texto "  Exportando BD '$DB_LOCAL' ..." 'Green'
        Caja-Bottom
        # Fila para "Conectando a MySQL local..." (fila 4) y barra (fila 5)
        $filaConectando = 4
        $filaBarra = 5
        Dibujar-Sombra 8
        Animacion-Puntos "      Conectando a MySQL local" 1.2 $filaConectando
        Animacion-BarraProgreso 1.5 $filaBarra
        Write-Host ""

        $env:DB_NAME = $DB_LOCAL
        $env:BD_USER = $USUARIO
        $env:BD_PASS = $passText
        $env:BD_OUT  = $salida
        if ($esLocal) {
            $env:BD_DEST = 'LOCAL (TransNuBeT_nomina)'
            $env:BD_CREATE = '1'
        } else {
            $env:BD_DEST = 'if0_42708110_transnubet_nomina'
            $env:BD_CREATE = '0'
        }

        $phpOut = Join-Path $env:TEMP ("php_out_" + [guid]::NewGuid().ToString('N') + ".txt")
        $phpErr = Join-Path $env:TEMP ("php_err_" + [guid]::NewGuid().ToString('N') + ".txt")
        try {
            $oldErrorAction = $ErrorActionPreference
            $ErrorActionPreference = 'SilentlyContinue'
            $proc = Start-Process -FilePath $PHP_EXE -ArgumentList $phpFile -Wait -NoNewWindow -RedirectStandardOutput $phpOut -RedirectStandardError $phpErr -PassThru
            $ErrorActionPreference = $oldErrorAction
            $codigoPhp = $proc.ExitCode

            $outContent = ""
            if (Test-Path $phpOut) {
                $outContent = Get-Content $phpOut -Raw
                if (-not [string]::IsNullOrWhiteSpace($outContent)) {
                    Write-Host ("  " + $outContent) -ForegroundColor Gray
                }
            }
            $errContent = ""
            if (Test-Path $phpErr) {
                $errContent = Get-Content $phpErr -Raw
            }
        } finally {
            Remove-Item $phpOut, $phpErr -Force -ErrorAction SilentlyContinue
        }

        if ($codigoPhp -ne 0) {
            Clear-Host
            MostrarNotificacion -Title "SISGESNOM :: ERROR DE CONEXION" -Text "No se pudo conectar a la base de datos local. Revise usuario/contrasena." -Level "error" -Timeout 6000

            # Caja de error con fondo amarillo (se restaura el azul al terminar)
            $fondoOld = $script:FondoRelleno
            $colorOld = $script:ColorRelleno
            $script:FondoRelleno = [ConsoleColor]::Yellow
            $script:ColorRelleno = [ConsoleColor]::Yellow
            Caja-Top
            Barra-Titulo "  SISGESNOM :: ERROR DE CONEXION" 
            Caja-Texto "  No se pudo conectar a la base de datos local." 'Black'
            Caja-Linea
            if (-not [string]::IsNullOrWhiteSpace($errContent)) {
                Caja-Texto ("  " + $errContent.Trim()) 'Black'
            } else {
                Caja-Texto "  Error desconocido. Revise sus credenciales." 'Black'
            }
            Caja-Linea
            Caja-Texto "  Asegurese de que:" 'Black'
            Caja-Texto "   - El servidor MySQL este en ejecucion." 'Black'
            Caja-Texto "   - El usuario y contrasena sean correctos." 'Black'
            Caja-Texto "   - La base de datos '$DB_LOCAL' exista." 'Black'
            Caja-Bottom
            Dibujar-Sombra
            $script:FondoRelleno = $fondoOld
            $script:ColorRelleno = $colorOld
            Sonido-Error
            Start-Sleep -Milliseconds 400
            Write-Host ""
            Read-Host "  Presione Enter para volver al menu"
            continue
        }

        # ----- Éxito -----
        $tamaño = (Get-Item $salida -ErrorAction SilentlyContinue).Length

        Clear-Host
        if ($esLocal) {
            MostrarNotificacion -Title "SISGESNOM :: EXPORTACION LOCAL" -Text ("Exportacion local OK. Archivo: " + $salida + " (" + [math]::Round($tamaño/1MB,2) + " MB)") -Level "success" -Timeout 6000
            # Marco completo, luego "Finalizando" pisa la fila 3
            Caja-Top
            Barra-Titulo "  SISGESNOM :: EXPORTACION LOCAL COMPLETADA"
            Caja-Sep
            Caja-Linea
            Caja-Linea
            Caja-Texto ("  OK. Archivo: " + $salida) 'Green'
            Caja-Texto ("  Tamano  : " + [math]::Round($tamaño/1MB,2) + " MB") 'Green'
            Caja-Sep
            Caja-Texto "  Dump local estilo phpMyAdmin generado." 'Cyan'
            Caja-Texto "  No se envia a InfinityFree." 'DarkGray'
            Caja-Bottom
            $filaFinal = 3
            Animacion-Puntos "      Finalizando" 1 $filaFinal
            Setup-Done $filaFinal
            Dibujar-Sombra
        } else {
            MostrarNotificacion -Title "SISGESNOM :: EXPORTACION COMPLETADA" -Text ("Exportacion OK. Archivo: " + $salida + " (" + [math]::Round($tamaño/1MB,2) + " MB)") -Level "success" -Timeout 6000
            # Marco completo, luego "Finalizando" pisa la fila 3
            Caja-Top
            Barra-Titulo "  SISGESNOM :: EXPORTACION COMPLETADA"
            Caja-Sep
            Caja-Linea
            Caja-Linea
            Caja-Texto ("  OK. Archivo: " + $salida) 'Green'
            Caja-Texto ("  Tamano  : " + [math]::Round($tamaño/1MB,2) + " MB") 'Green'

            if ($abrir) {
                Start-Process notepad $salida
            }

            Caja-Sep
            Caja-Texto "  1. phpMyAdmin remoto (Importar): " 'Yellow'
            if ($minimizado) {
                # Intento de auto-carga: abre directo en la pestana Importar de la BD.
                $urlImport = "https://php-myadmin.net/index.php?route=/database/import&db=if0_42708110_transnubet_nomina"
                Start-Process $urlImport
                Set-Clipboard -Value $salida
                Caja-Texto "     $urlImport" 'Cyan'
                Caja-Texto "  2. Si pide login, entre a InfinityFree y repita." 'White'
                Caja-Texto "     'Elegir archivo' -> 'Continuar' (ruta copiada)." 'White'
                Caja-Texto "     (BigDump disponible si el .sql es muy grande)." 'Yellow'
            } else {
                Caja-Texto "     https://php-myadmin.net/db_structure.php?db=if0_42708110_transnubet_nomina" 'Cyan'
                Caja-Texto "  2. Login -> 'Importar' -> elegir el .sql -> Continuar." 'White'
            }
            Caja-Bottom
            $filaFinal = 3
            Animacion-Puntos "      Finalizando" 1 $filaFinal
            Setup-Done $filaFinal
            Dibujar-Sombra
        }
        Sonido-Enter
        Write-Host ""
        Read-Host "  Presione Enter para volver al menu"
    } finally {
        Remove-Item Env:DB_NAME, Env:BD_USER, Env:BD_PASS, Env:BD_OUT, Env:BD_DEST, Env:BD_CREATE -ErrorAction SilentlyContinue
        Remove-Item $phpFile -Force -ErrorAction SilentlyContinue
    }
}