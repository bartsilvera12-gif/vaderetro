#!/bin/bash
# ============================================================================
# VADE RETRO — receta de fotos
#
# Regenera TODOS los assets del sitio a partir de las tomas originales.
# Uso:   ./build-fotos.sh /ruta/a/los/originales
#
# Los originales se esperan con los nombres de abajo (ORIGEN). Si llegan con
# otros nombres, primero identificalos comparando contra los assets actuales
# con una huella perceptual, no a ojo:
#
#   magick ORIGINAL -resize 16x16! -colorspace gray -depth 8 txt: | md5
#   # o, mas fino:
#   magick compare -metric RMSE ORIGINAL_reducido ASSET_reducido null:
#
# Cada bloque dice de que toma sale cada asset. Los recortes van sobre la
# medalla a proposito: en un cuadro chico la pieza entera no se lee, y la
# medalla es lo que identifica al producto.
#
# El recorte se auto-ajusta si la foto nueva tiene otro alto (por ejemplo si
# ya viene sin la franja de marca de agua): la funcion crop() sube el offset
# en vez de salirse del cuadro.
# ============================================================================
set -e
SRC="${1:?uso: ./build-fotos.sh /ruta/a/originales}"
OUT="$(dirname "$0")/assets"

full(){ magick "$SRC/$1" -resize '1100x1100>' -quality 84 "$OUT/$2.webp"; }

crop(){ src="$SRC/$1"; w=$2; h=$3; x=$4; y=$5; out=$6; sz=$7
  H=$(magick identify -format "%h" "$src"); W=$(magick identify -format "%w" "$src")
  [ $((y+h)) -gt "$H" ] && y=$((H-h)); [ "$y" -lt 0 ] && { y=0; h=$H; }
  [ $((x+w)) -gt "$W" ] && x=$((W-w)); [ "$x" -lt 0 ] && { x=0; w=$W; }
  magick "$src" -crop "${w}x${h}+${x}+${y}" +repage -resize "${sz}x${sz}>" -quality 84 "$OUT/$out.webp"; }

# --- FOTOS DE PRODUCTO -----------------------------------------------------
# La -1 es la principal de cada ficha: la toma mas limpia y de cuerpo entero.
# Las demas son miniaturas, en orden de utilidad para entender la pieza.

# Cristal cortado — dos colores: miel (1 y 1.1) y azul con miel (8 y 8.1)
full "articulo 1.jpeg"    cristal-1
full "articulo 1.1.jpeg"  cristal-2
full "articulo 8.jpeg"    cristal-3
full "articulo 8.1.jpeg"  cristal-4

# Doble medalla — 7.2 es la de fondo crema, la mas limpia
full "articulo 7.2.jpeg"  doble-1
full "articulo 7.jpeg"    doble-2
full "articulo 7.1.jpeg"  doble-3

# Imagen laminada — 6 muestra la cara con la imagen a color; 6.1 el reverso
full "articulo 6.jpeg"    laminada-1
full "articulo 6.1.jpeg"  laminada-2
full "articulo 6.2.jpeg"  laminada-3

# Medallon giratorio — 5.3 lo muestra girado, que es lo que lo distingue
full "articulo 5.jpeg"    giratorio-1
full "articulo 5.1.jpeg"  giratorio-2
full "articulo 5.2.jpeg"  giratorio-3
full "articulo 5.3.jpeg"  giratorio-4

# Tejida — 4.1 es la de estudio, fondo parejo
full "articulo 4.1.jpeg"  tejida-1
full "articulo 4.jpeg"    tejida-2
full "articulo 4.2.jpeg"  tejida-3

# Decenario en madera — 2.3 es la foto de producto; 2 y 2.1 los dos modos
full "articulo2.3.jpeg"   decenario-1
full "articulo2.jpeg"     decenario-2
full "articulo2.1.jpeg"   decenario-3
full "articulo2.2.jpeg"   decenario-4

# Cordon largo — 3.3 sobre pared clara, entera
full "articulo3.3.jpeg"   cordon-1
full "articulo3.jpeg"     cordon-2
full "articulo3.1.jpeg"   cordon-3
full "articulo3.2.jpeg"   cordon-4

# --- TARJETAS DEL CATALOGO -------------------------------------------------
# Cuadradas y cerradas sobre la medalla: el hueco de la grilla es apaisado y
# de 250px, ahi la foto entera se ve como un hilito.
crop "articulo 1.jpeg"     936 1000    0 99999 cristal-card    900   # abajo del todo
crop "articulo 7.2.jpeg"   620  620  135   467 doble-card      900
crop "articulo 6.jpeg"     700  700  192   828 laminada-card   900
crop "articulo 5.2.jpeg"  1000 1000   74  1016 giratorio-card  900
crop "articulo 4.1.jpeg"  1170 1170    0   700 tejida-card     900   # el conjunto, son 5 medallas
crop "articulo2.3.jpeg"    900  900    0  1148 decenario-card  900
crop "articulo3.2.jpeg"    900  900    0   128 cordon-card     900

# --- HERO Y MENU -----------------------------------------------------------
# Huecos anchos: una foto vertical pierde el tercio de abajo, justo donde
# esta la medalla. Por eso llevan encuadre propio.
crop "articulo 5.jpeg"     923  900    0   790 hero-medalla   1200
crop "articulo 8.jpeg"    1100 1000    0  1046 menu-medalla   1300

echo "listo: $(ls "$OUT"/*.webp | wc -l | tr -d ' ') archivos en $OUT"

# --- NO SALE DE ACA --------------------------------------------------------
# assets/inscripciones.webp es la lamina del significado de la medalla, que
# vino aparte. No la toca esta receta: si se corre completa, esa se conserva.
