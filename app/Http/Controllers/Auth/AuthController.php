<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Aacotroneo\Saml2\Saml2Auth;
use Illuminate\Http\Request;


class AuthController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('verify.auth')->except('logout');
    }

    public function login(){
        /*--------------------------------
        | Antes de llegar aqui, pasa por el middleware 
        | verify.auth que hace la verificación e inten-
        | hacer el loguin mediante la federación.
        --------------------------------*/        
        return redirect('/');
    }

    public function logout(){
        if ( env('SAML_SIMULATOR', false) == true ) {
            /*--------------------------------
            | SAML Simulado
            | En caso de estarse simulando el login
            | solo borra los datos de sesión y re-
            | direcciona.
            --------------------------------*/
            \Auth::logout();
            \Session::forget('saml');
            \Session::save();
            return redirect('/');
        }else{
            /*--------------------------------
            | SAML Real
            | En caso de estar logueado con SAML
            | ejecuta las intrucciones para cerrar
            | la sesión del IDP definido en config/saml2
            --------------------------------*/
            $idp = "test";
            $saml2Auth = new Saml2Auth(Saml2Auth::loadOneLoginAuthFromIpdConfig( $idp ));
            return $saml2Auth->logout('/');
        }        
    }
}
