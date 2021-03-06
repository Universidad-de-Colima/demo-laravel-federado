<?php

namespace App\Listeners;

use Aacotroneo\Saml2\Events\Saml2LogoutEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogoutListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  Saml2LogoutEvent  $event
     * @return void
     */
    public function handle(Saml2LogoutEvent $event)
    {
        /*
        | Cerramos la sesión del usuario logueado
        */
        \Auth::logout();
        \Session::forget('saml');
        \Session::save();
    }
}
