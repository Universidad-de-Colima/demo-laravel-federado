<?php

namespace App\Http\Middleware;

use Closure;

use Illuminate\Support\Facades\Auth;
use Aacotroneo\Saml2\Saml2Auth;
use \App\Models\User;

class VerifyAuthSaml
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ( $request->ajax() ){
            return response('Unauthorized.', 401);
        }else{
            /*---------------------------------
            | Checa que no sea guest (Auth) es
            | decir, que esté autenticado.
            ---------------------------------*/

            if ( Auth::guest() ) {
                /*---------------------------------
                | Si es guest, se debe iniciar el 
                | proceso de autenticación, comen-
                | zando con simpleSAML 2.
                ---------------------------------*/
                
                if ( !\Session::has('saml') ) {
                    /*---------------------------------
                    | Si la variable de sesión con los
                    | datos de SAML no existe, pedimos
                    | autenticación al IDP de UCOL
                    ---------------------------------*/
                
                    if ( env('SAML_SIMULATOR', false) == true ) {
                        /*--------------------------------
                        | Para simular SAML
                        --------------------------------*/

                        $userData = [
                            'attributes' => [
                                "uCorreo"   => [env('SAML_SIMULATOR_EMAIL', 'maro@ucol.mx')],
                                "uNombre"   => [env('SAML_SIMULATOR_NAME', 'Rodriguez Ortiz Miguel Angel')],
                                "sn"        => [env('SAML_SIMULATOR_FIRSTNAME', 'Miguel Angel')],
                                "givenName" => [env('SAML_SIMULATOR_LASTNAME', 'Rodríguez Ortiz')],
                            ]
                        ];

                        \Session::put('saml', $userData);
                        return redirect(\URL::full());
                    }else{
                        /*--------------------------------
                        | SAML Real
                        --------------------------------*/
                        $idp = "test";
                        $saml2Auth = new Saml2Auth(Saml2Auth::loadOneLoginAuthFromIpdConfig( $idp ));
                        return $saml2Auth->login(\URL::full());
                    }

                }else{
                    /*---------------------------------
                    | Si la variable de sesión con los
                    | datos de SAML, SÌ existe, inicia-
                    | mos proceso de logueo (o alta de
                    | usaurio segun el caso).
                    ---------------------------------*/
                    
                    $userData = \Session::get('saml');

                    /*---------------------------------
                    | Se busca al usuario logueado con 
                    | SAML, en  la  Base de Datos  del
                    | sistema.
                    ---------------------------------*/
                    $user = User::where('email', $userData['attributes']['uCorreo'][0])->first();

                    if ( is_null( $user ) ) {
                        if( env('CREATE_NEW_USER_RECORD', false) == true ){
                            /*---------------------------------
                            | Si el usuario no existe, se pro-
                            | cede a la generación del registro
                            | (opcional).
                            ---------------------------------*/                        
                            $user               = new User;
                            $user->first_name   = $userData['attributes']['sn'][0];
                            $user->last_name    = $userData['attributes']['givenName'][0];
                            $user->email        = $userData['attributes']['uCorreo'][0];
                            $user->save();
                        }else{
                            /*---------------------------------
                            | Usuario con acceso a la federación
                            | pero no en la base de datos del
                            | sistema.
                            ---------------------------------*/

                            return redirect('/')->withErrors(['message' =>'El usuario ' .$userData['attributes']['givenName'][0]. ' no tiene permisos en el sistema. Si lo desea <a href="/logout" class="underline text-gray-900 dark:text-white">cierre sesión en Federación UCOL</a> e intente con otra cuenta ']);    
                        }
                    }

                    /*---------------------------------
                    | Si inicia el proceso de logueo
                    | del usuario —mecanismo Laravel—.
                    ---------------------------------*/  

                    Auth::login($user);
                }
            }

        }
        return $next($request);
    }
}
