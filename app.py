from flask import Flask, render_template, request
# Importar la configuración de la base de datos (simulada aquí)
# from . import db 

# Inicialización de la aplicación Flask
app = Flask(__name__)

# Configuración de paginación
PER_PAGE = 12

# =================================================================
# SIMULACIÓN DE LA CONSULTA DE PRODUCTOS Y CONTEO TOTAL
# =================================================================

# NOTA IMPORTANTE: En un proyecto real, la lógica de 'Producto' y 'db.session'
# provendría de tu ORM (como SQLAlchemy) y se conectaría a tu base de datos MySQL.
# Aquí se usa el método de paginación manual (LIMIT/OFFSET).

def get_paginated_productos(offset, per_page, query_method):
    """
    Simula la obtención de productos paginados y el conteo total desde la DB.
    
    :param query_method: La función ORM o de conexión usada para ejecutar queries.
    """
    # 1. Obtener el conteo total de ítems (sin filtros aplicados en este ejemplo)
    # total_items = db.session.query(Producto).count() 
    
    # *** SIMULACIÓN TEMPORAL DE DATOS ***
    # Asumimos que la consulta total es 100 productos para calcular las páginas
    total_items = 100 
    
    # 2. Obtener la porción de productos para la página actual
    # productos = db.session.query(Producto).limit(per_page).offset(offset).all()

    # *** SIMULACIÓN TEMPORAL DEL RESULTADO ***
    productos = [{'id': i + offset, 'nombre': f'Producto {i + offset}'} for i in range(per_page)]
    
    return productos, total_items

# =================================================================
# RUTA PRINCIPAL DEL CATÁLOGO (Método de Paginación Manual)
# =================================================================

@app.route('/catalogo')
def catalogo():
    # 1. Obtener el número de página de la URL (default 1)
    page = request.args.get('page', 1, type=int)
    offset = (page - 1) * PER_PAGE

    # Lógica de filtros (si existiera: search, category, etc.) iría aquí.
    
    productos, total_items = get_paginated_productos(offset, PER_PAGE, None)
    
    # 2. Calcular el total de páginas
    total_pages = (total_items + PER_PAGE - 1) // PER_PAGE

    # 3. Pasar los datos y la información de paginación a la plantilla
    return render_template('catalogo.html',
                           productos=productos,
                           total_pages=total_pages,
                           current_page=page)

if __name__ == '__main__':
    # Asegúrate de que tu modelo 'Producto' y la sesión 'db.session' estén disponibles
    # antes de ejecutar la aplicación.
    app.run(debug=True)