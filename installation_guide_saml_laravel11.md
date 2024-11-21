
# Guía de instalación: SAML2 en Laravel 11

Este documento describe paso a paso cómo configurar la federación SAML2 en Laravel 11 utilizando el paquete `24slides/laravel-saml2`, siguiendo las mejores prácticas.

---

## **Requisitos previos**

- Tener Laravel 11 instalado en tu entorno de desarrollo.
- Certificados SAML (`.crt` y `.key`).
- Acceso para configurar el Proveedor de Identidad (IDP) con los datos del Proveedor de Servicios (SP).

---

## **Pasos de instalación**

### 1. Instalar el paquete `laravel-saml2`

Ejecuta el siguiente comando para instalar el paquete:

```bash
composer require 24slides/laravel-saml2
```

---

### 2. Publicar los archivos de configuración

Publica los archivos de configuración y vistas del paquete:

```bash
php artisan vendor:publish --provider="Slides\Saml2\ServiceProvider"
```

Esto generará el archivo de configuración en `config/saml2.php`.

---

### 3. Ejecutar las migraciones

Crea las tablas necesarias en la base de datos ejecutando:

```bash
php artisan migrate
```

---

### 4. Crear un tenant SAML

Crea un tenant (entidad) para tu aplicación utilizando el siguiente comando Artisan:

```bash
php artisan saml2:create-tenant \
    --key='ucol' \
    --entityId='https://dgre2.ucol.mx/idp/' \
    --loginUrl='https://dgre2.ucol.mx/simplesaml/saml2/idp/SSOService.php' \
    --logoutUrl='https://dgre2.ucol.mx/simplesaml/saml2/idp/SingleLogoutService.php' \
    --x509cert='<CERTIFICADO_BASE64>'
```

Reemplaza `<CERTIFICADO_BASE64>` con el contenido del certificado proporcionado por el IDP codificado en Base64.

---

### 5. Copiar certificados SAML

Copia los archivos `.crt` y `.key` en la carpeta:

```
vendor/onelogin/php-saml/certs
```

Asegúrate de que estos archivos estén almacenados de forma segura y sean accesibles por la aplicación.

---

### 6. Configurar el IDP

Proporciona la siguiente configuración al administrador del IDP:

```php
$metadata['http://localhost/saml2/{UUID}/metadata'] = array (
  'AssertionConsumerService' => 'http://localhost/saml2/{UUID}/acs',
  'SingleLogoutService' => 'http://localhost/saml2/{UUID}/sls',
  'certData' => '<CERTIFICADO_BASE64>',
);
```

Reemplaza `{UUID}` con el identificador único generado para tu tenant y `<CERTIFICADO_BASE64>` con el contenido del certificado en Base64.

---

### 7. Crear el `EventServiceProvider`

Crea el archivo `app/Providers/EventServiceProvider.php` con el siguiente contenido:

```php
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Slides\Saml2\Events\SignedIn;
use Slides\Saml2\Events\SignedOut;
use App\Models\User;

class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(SignedIn::class, function (SignedIn $event) {
            try {
                $this->handleSamlSignedIn($event);
            } catch (\Throwable $e) {
                Log::error('Error manejando el evento SignedIn: ' . $e->getMessage());
                return Redirect::route('login')->with('error', 'Error procesando el inicio de sesión.');
            }
        });

        Event::listen(SignedOut::class, function (SignedOut $event) {
            Auth::logout();
            Session::invalidate();
            Session::regenerateToken();
            Session::save();
        });
    }

    private function handleSamlSignedIn(SignedIn $event): void
    {
        $samlUser = $event->auth->getSaml2User();
        $userData = $samlUser->getAttributes();

        $user = User::where('email', $userData['uCorreo'][0] ?? null)->first();

        if (!$user) {
            throw new \Exception('Usuario no registrado.');
        }

        Auth::login($user);
        Session::put('saml_logged_in', true);
        Session::regenerate();
    }
}
```

---

### 8. Configurar middleware en `config/saml2.php`

Agrega el middleware `saml` al archivo de configuración:

```php
'routesMiddleware' => ['saml'],
```

---

### 9. Agregar el grupo de middleware en `bootstrap/app.php`

Abre el archivo `bootstrap/app.php` y agrega el grupo de middleware `saml`:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->group('saml', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
        ]);
    })
    ->create();
```

---

### 10. Configurar rutas en `.env`

Agrega las rutas de login y logout en el archivo `.env`:

```env
SAML2_LOGOUT_URL="/login"
SAML2_LOGIN_URL="/"
```

---

## **Pruebas finales**

1. Verifica las rutas SAML activas ejecutando:

```bash
php artisan route:list
```

2. Accede a la URL configurada para el inicio de sesión SAML y realiza las pruebas.

---

Este manual asegura una configuración completa y segura de SAML2 en Laravel 11 siguiendo las mejores prácticas.
