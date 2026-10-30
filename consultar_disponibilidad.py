# ============================================================
# SISTEMA DE GESTIÓN DE PRÉSTAMOS DE EQUIPOS Y MATERIAL EDUCATIVO
# Funcionalidad: Consulta de disponibilidad de recursos
# Entorno: Google Colab
# ============================================================

import ipywidgets as widgets
from IPython.display import display, HTML, clear_output
from html import escape

# ------------------------------------------------------------
# PALETA DE COLORES PASTEL
# ------------------------------------------------------------

CREMA = "#FFF8ED"
LILA = "#E9DDF5"
LILA_OSCURO = "#8064A2"
CELESTE = "#DCEFFA"
CELESTE_OSCURO = "#5B91B8"
CAFE = "#806653"
CAFE_CLARO = "#EDE0D1"
BLANCO = "#FFFFFF"
VERDE = "#E2F2E4"
ROJO = "#F8E3E5"

# ------------------------------------------------------------
# ESTILOS VISUALES
# ------------------------------------------------------------

display(HTML(f"""
<style>
body {{
    background-color: {CREMA};
}}

.jp-Notebook {{
    background-color: {CREMA} !important;
}}

.widget-label {{
    color: {CAFE} !important;
    font-weight: 600 !important;
}}

input, select {{
    background-color: {BLANCO} !important;
    border: 1px solid {LILA} !important;
    border-radius: 10px !important;
    color: {CAFE} !important;
    padding: 8px !important;
}}

.pastel-btn button {{
    background: {LILA} !important;
    color: {LILA_OSCURO} !important;
    border: 1px solid #D7C4E9 !important;
    border-radius: 12px !important;
    font-weight: bold !important;
    min-height: 42px !important;
    transition: 0.2s;
}}

.pastel-btn button:hover {{
    background: {CELESTE} !important;
}}

.secondary-btn button {{
    background: {CELESTE} !important;
    color: {CELESTE_OSCURO} !important;
    border: 1px solid #B9DDF1 !important;
    border-radius: 12px !important;
    font-weight: bold !important;
    min-height: 42px !important;
}}

.pastel-card {{
    background: {BLANCO};
    border: 1px solid {LILA};
    border-radius: 18px;
    padding: 20px;
    margin: 8px 0;
    box-shadow: 0 4px 12px rgba(128,100,162,0.08);
}}

.pastel-title {{
    color: {LILA_OSCURO};
    font-size: 27px;
    font-weight: bold;
    margin-bottom: 8px;
}}

.pastel-subtitle {{
    color: {CAFE};
    font-size: 14px;
}}

.section-title {{
    color: {LILA_OSCURO};
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 14px;
}}

.status-badge {{
    display: inline-block;
    padding: 8px 14px;
    border-radius: 20px;
    font-weight: bold;
}}
</style>
"""))

# ------------------------------------------------------------
# ENCABEZADO
# ------------------------------------------------------------

display(HTML(f"""
<div style="
    background:linear-gradient(135deg, {LILA}, {CELESTE});
    padding:30px 20px;
    border-radius:22px;
    text-align:center;
    border:1px solid #D8CBE8;
    margin-bottom:15px;
">
    <div style="font-size:38px;">🎓 📚</div>
    <div class="pastel-title">
        Sistema de Gestión de Préstamos
    </div>
    <div style="color:{CAFE};font-size:17px;font-weight:bold;">
        Equipos, Laboratorios y Material Educativo
    </div>
    <div class="pastel-subtitle" style="margin-top:10px;">
        Universidad Nacional del Altiplano de Puno
    </div>
</div>
"""))

# ------------------------------------------------------------
# INVENTARIO DE EJEMPLO
# ------------------------------------------------------------

# Estos datos son ejemplos para probar la funcionalidad.
# No representan el inventario real de la universidad.

recursos = {
    "Proyector multimedia": "Disponible",
    "Microscopio": "Prestado",
    "Equipo de laboratorio": "Disponible",
    "Material educativo": "Prestado",
    "Instrumento de medición": "Disponible"
}

# ------------------------------------------------------------
# FUNCIONALIDAD PRINCIPAL
# ------------------------------------------------------------

def consultar_disponibilidad(nombre_recurso):
    """
    Consulta el estado de un recurso registrado.
    Devuelve su estado o un mensaje si no existe.
    """

    nombre_recurso = nombre_recurso.strip()

    if not nombre_recurso:
        return "error", "Debes ingresar el nombre del recurso."

    if nombre_recurso not in recursos:
        return "error", "El recurso no se encuentra registrado."

    estado = recursos[nombre_recurso]

    if estado == "Disponible":
        return "disponible", "El recurso está disponible para préstamo."

    return "prestado", "El recurso se encuentra actualmente prestado."


# ------------------------------------------------------------
# CAMBIAR ESTADO DE UN RECURSO
# ------------------------------------------------------------

def cambiar_estado_recurso(nombre_recurso, nuevo_estado):
    """
    Actualiza el estado de un recurso registrado.
    """

    if nombre_recurso not in recursos:
        return False, "El recurso no se encuentra registrado."

    if nuevo_estado not in ("Disponible", "Prestado"):
        return False, "Selecciona un estado válido."

    recursos[nombre_recurso] = nuevo_estado

    return True, "El estado del recurso se actualizó correctamente."


# ------------------------------------------------------------
# CONTROLES DE LA INTERFAZ
# ------------------------------------------------------------

campo_nombre = widgets.Text(
    value="",
    placeholder="Escribe el nombre del recurso...",
    description="Recurso:",
    style={"description_width": "initial"},
    layout=widgets.Layout(width="100%", height="42px")
)

lista_recursos = widgets.Dropdown(
    options=[("Selecciona un recurso", "")] +
            [(nombre, nombre) for nombre in recursos],
    value="",
    description="Ejemplos:",
    style={"description_width": "initial"},
    layout=widgets.Layout(width="100%", height="42px")
)

boton_consultar = widgets.Button(
    description="Consultar disponibilidad",
    icon="search",
    layout=widgets.Layout(width="100%")
)
boton_consultar.add_class("pastel-btn")

salida = widgets.Output()

# ------------------------------------------------------------
# MOSTRAR RESULTADOS VISUALES
# ------------------------------------------------------------

def mostrar_resultado(tipo, titulo, mensaje, recurso=""):

    colores = {
        "disponible": VERDE,
        "prestado": CELESTE,
        "error": ROJO,
        "actualizado": LILA
    }

    iconos = {
        "disponible": "✓",
        "prestado": "◷",
        "error": "!",
        "actualizado": "✓"
    }

    color = colores.get(tipo, BLANCO)
    icono = iconos.get(tipo, "•")

    recurso_html = ""

    if recurso:
        recurso_html = f"""
        <p style="margin:8px 0;color:{CAFE};">
            <b>Nombre del recurso:</b> {escape(recurso)}
        </p>
        """

    display(HTML(f"""
    <div style="
        background:{color};
        border:1px solid {LILA};
        border-radius:16px;
        padding:20px;
        margin-top:12px;
        color:{CAFE};
    ">
        <div style="font-size:24px;color:{LILA_OSCURO};">
            <b>{icono} {titulo}</b>
        </div>
        {recurso_html}
        <p style="font-size:15px;margin-bottom:0;">
            {escape(mensaje)}
        </p>
    </div>
    """))


# ------------------------------------------------------------
# ACCIÓN DEL BOTÓN CONSULTAR
# ------------------------------------------------------------

def ejecutar_consulta(boton):

    with salida:
        clear_output(wait=True)

        nombre = campo_nombre.value.strip()

        tipo, mensaje = consultar_disponibilidad(nombre)

        if tipo == "error":
            mostrar_resultado(
                "error",
                "No se pudo realizar la consulta",
                mensaje,
                nombre
            )

        elif tipo == "disponible":
            mostrar_resultado(
                "disponible",
                "Recurso disponible",
                mensaje,
                nombre
            )

        else:
            mostrar_resultado(
                "prestado",
                "Recurso prestado",
                mensaje,
                nombre
            )


boton_consultar.on_click(ejecutar_consulta)


# ------------------------------------------------------------
# SELECCIÓN DE RECURSOS DE EJEMPLO
# ------------------------------------------------------------

def seleccionar_recurso(cambio):

    if cambio["new"]:
        campo_nombre.value = cambio["new"]


lista_recursos.observe(seleccionar_recurso, names="value")


# ------------------------------------------------------------
# INTERFAZ: CONSULTAR DISPONIBILIDAD
# ------------------------------------------------------------

display(HTML(f"""
<div class="pastel-card">
    <div class="section-title">
        🔎 Consultar disponibilidad
    </div>
    <p style="color:{CAFE};">
        Escribe el nombre del recurso o selecciona uno de los ejemplos.
    </p>
</div>
"""))

display(campo_nombre)
display(lista_recursos)
display(boton_consultar)
display(salida)

# ------------------------------------------------------------
# SECCIÓN PARA ACTUALIZAR EL ESTADO
# ------------------------------------------------------------

display(HTML(f"""
<div style="
    background:{CAFE_CLARO};
    border:1px solid #D9C5AE;
    padding:20px;
    border-radius:18px;
    margin-top:25px;
">
    <div style="color:{CAFE};font-size:20px;font-weight:bold;">
        ⚙️ Administración de recursos
    </div>
    <p style="color:{CAFE};">
        Permite actualizar el estado de un recurso de ejemplo.
    </p>
</div>
"""))

recurso_admin = widgets.Dropdown(
    options=list(recursos.keys()),
    description="Recurso:",
    style={"description_width": "initial"},
    layout=widgets.Layout(width="100%", height="42px")
)

estado_admin = widgets.Dropdown(
    options=["Disponible", "Prestado"],
    value="Disponible",
    description="Nuevo estado:",
    style={"description_width": "initial"},
    layout=widgets.Layout(width="100%", height="42px")
)

boton_actualizar = widgets.Button(
    description="Actualizar estado",
    icon="refresh",
    layout=widgets.Layout(width="100%")
)
boton_actualizar.add_class("secondary-btn")

salida_admin = widgets.Output()


def ejecutar_actualizacion(boton):

    with salida_admin:
        clear_output(wait=True)

        nombre = recurso_admin.value
        nuevo_estado = estado_admin.value

        correcto, mensaje = cambiar_estado_recurso(
            nombre,
            nuevo_estado
        )

        if correcto:
            # Sincronizar la lista de ejemplos
            lista_recursos.options = [
                ("Selecciona un recurso", "")
            ] + [(n, n) for n in recursos]

            mostrar_resultado(
                "actualizado",
                "Actualización realizada",
                f"{mensaje} Nuevo estado: {nuevo_estado}.",
                nombre
            )
        else:
            mostrar_resultado(
                "error",
                "No se pudo actualizar",
                mensaje,
                nombre
            )


boton_actualizar.on_click(ejecutar_actualizacion)

display(recurso_admin)
display(estado_admin)
display(boton_actualizar)
display(salida_admin)

# ------------------------------------------------------------
# PIE DE PÁGINA
# ------------------------------------------------------------

display(HTML(f"""
<div style="
    text-align:center;
    padding:20px;
    margin-top:20px;
    color:{CAFE};
    border-top:1px solid {LILA};
">
    <b>Proyecto de Ingeniería de Software</b><br>
    <span style="font-size:12px;">
        Consulta de disponibilidad de recursos · Prototipo académico
    </span>
</div>
"""))
