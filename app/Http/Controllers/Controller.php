<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Salono Backend API",
 *     description="Documentation de l'API Salono Backend",
 *     @OA\Contact(
 *         email="support@salono.com"
 *     )
 * )
 *
 * @OA\Server(
 *     url="http://127.0.0.1:8000",
 *     description="Serveur de développement local"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Utilisez le token JWT obtenu lors de la connexion"
 * )
 */
abstract class Controller
{
    //
}
