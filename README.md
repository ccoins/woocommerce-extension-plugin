# CCoin eCommerce integration

CCoins proporciona dos maneras de integrar tu eCommerce: por API o como medio de pago de Woocommerce. Tener en cuenta que es necesario tener tu ecommerce completamente configurado en CCoins.

## API Integration

Para lograr una exitosa integracion por API, se deben seguir los siguientes pasos y requerimientos.

### Requerimientos:
- Se debe contar con un perfil de eCommerce en la plataforma [CCoins](https://ccoins.io/)

### Instrucciones
#### Paso 1: Obten la URL de la API
Dirigete a la seccion **Integraciones** en tu ecommerce en CCoins y obten la URL y el API Key. Estos te seran necesarios para la integracion

### Integrar
La API de CCoins espera la siguiente informacion
- **Headers**: Se utiliza el metodo de autenticacion estandar. Se espera que el API Token este situado en el header `Authorization`. Por ejemlo
    ```json
        "headers": { "Authorization": "Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c" }
    ```
- **Body**: La informacion que se espera del lado del servidor es:
    - `data`: JSON que contiene la `description` del producto. Esta puede ser por ejemplo el nombre, o cualquier informacion que se considere relevante para la distincion del producto. Tambien contiene la `home_url`, que es la url a la cual se lo redirige al usuario. Un ejemplo de este campo es 
        ```json
        "data": { "description": "Lavarropas", "home_url": "https://www.ecommerce.com" }
        ```
    - `first_name`: Nombre del cliente
    - `last_name`: Apellido del cliente
    - `email`: Mail del cliente
    - `fiat_amount`: Monto fiat del producto

    Un ejemplo de un body valido puede ser
    ```json
    {
        "first_name": "Jhon",
        "last_name": "Doe",
        "email": "jhon.doe@mail.com",
        "fiat_amount": 107.75,
        "data": { "description": "Lavarropas", "home_url": "https://www.ecommerce.com" }
    }
    ```
- **Response**: Una vez hecho el request, la CCoins API retornara una URL valida hacia la cual usted debe redirigir al usuario final
    ```json
    {
        "redirect_url": "http://ccoins.io/ABCDE12345"
    }
    ```


## Integración del Plugin CCoins Gateway en tu eCommerce

### Requerimientos:
- Es necesario que el ecommerce cuente con [Woocommerce](https://es.wordpress.org/plugins/woocommerce/) integrado
- Se debe contar con un perfil de eCommerce en la plataforma [CCoins](https://ccoins.io/)

### Instrucciones
#### Paso 1: Descargar el Plugin

1. Descarga de este repositorio el .zip proporcionado
2. Ve a Administrador > Plugins > Añadir nuevo > Subir plugin. Alli es donde se debe insertar el .zip 
3. Ve a la seccion de Plugins instalados y activa el nuevo plugin

#### Paso 2: Configura la extension
Una vez activo el plugin, se debe configuar el mismo para que funcione de manera correcta.

La seccion de configuracion de CCoins cuenta con los siguientes campos configurables
1. **Titulo**: Titulo que hace referencia a CCoins como medio de pago
2. **Estado de orden**: El estado de la orden luego del checkout
3. **Descripcion**: Descripcion del metodo de pago
4. **Instrucciones**: Instrucciones al cliente final luego de la compra
5. **API Key**: API Key de tu ecommerce en CCoins para autenticar tu comercio

Los campos **Titulo** y **API Key** son de crucial importancia para que la extension funcione de manera correcta.

#### Paso 3: Uso
Listo! Ya podes empezar a utilizar el plugin. Recorda que cada orden que se cree la vas a ver reflejada tanto en el panel de control de Woocommerce como en el de CCoins. Para una mejor experiencia, recomendamos qu el estado de orden y sus accionables se lleven a cabo desde CCoins para una mejor experiencia de usuario

### Consideraciones importantes
Por default, la informacion de usuario se obtiene desde el formulario estandar de Woocommerce. Si por alguna razo, este ultimo sufre de alguna modificacion, se debe actualizar el codigo del plugin para que se tomen los campos deseados. Esto se puede lograr desde el editor de Plugins proporcionado por Wordpress
