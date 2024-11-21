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
    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        // Handle SAML2 SignedIn event
        Event::listen(SignedIn::class, function (SignedIn $event) {
            try {
                $this->handleSamlSignedIn($event);
            } catch (\Throwable $e) {
                Log::error('Error handling SignedIn event: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
                return Redirect::route('login')->with('error', 'Error al procesar el inicio de sesión.');
            }
        });

        // Handle SAML2 SignedOut event
        Event::listen(SignedOut::class, function (SignedOut $event) {
            $this->handleSamlSignedOut();
        });
    }

    /**
     * Handle the SAML SignedIn event.
     *
     * @param SignedIn $event
     * @return void
     * @throws \Exception
     */
    private function handleSamlSignedIn(SignedIn $event): void
    {
        $messageId = $event->auth->getLastMessageId();

        // Check if the messageId has already been processed
        if (Cache::has('saml_message_' . $messageId)) {
            throw new \Exception('El mensaje SAML ya ha sido procesado anteriormente.');
        }

        // Prevent replay attack by storing the messageId in the cache
        Cache::put('saml_message_' . $messageId, true, now()->addMinutes(5));

        $samlUser = $event->auth->getSaml2User();
        $userData = $this->extractUserData($samlUser);

        if (empty($userData['email'])) {
            throw new \Exception('No se encontró un correo válido en los atributos SAML.');
        }

        $user = User::where('email', $userData['email'])->first();

        if (!$user) {
            throw new \Exception('Usuario inexistente en el sistema, favor de registrarse.');
        }

        // Login the user
        Auth::login($user);

        // Store the session and regenerate for security
        Session::put('saml_logged_in', true);
        Session::regenerate();
    }

    /**
     * Handle the SAML SignedOut event.
     *
     * @return void
     */
    private function handleSamlSignedOut(): void
    {
        Auth::logout();

        // Invalidate and regenerate session
        Session::invalidate();
        Session::regenerateToken();
        Session::save();
    }

    /**
     * Extract user data from the SAML response.
     *
     * @param \Slides\Saml2\Models\User $samlUser
     * @return array
     */
    private function extractUserData($samlUser): array
    {
        $attributes = $samlUser->getAttributes();

        return [
            'id' => $samlUser->getUserId(),
            'email' => $attributes['uCorreo'][0] ?? null,
            'assertion' => $samlUser->getRawSamlAssertion(),
        ];
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
