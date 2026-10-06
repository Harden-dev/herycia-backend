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
    private const GENERIC_ERROR = 'Une erreur interne est survenue. Veuillez réessayer.';

    /**
     * Message d'erreur renvoyé au client (audit H6).
     *
     * Seuls les messages métier, écrits volontairement par l'application, sont exposés :
     * exceptions du namespace App ou levées directement en Exception / RuntimeException /
     * InvalidArgumentException. Les erreurs techniques (SQL, extensions, librairies tierces)
     * sont remplacées par un message générique ; le détail reste dans les logs.
     */
    protected function safeMessage(\Throwable $e): string
    {
        if (config('app.debug')) {
            return $e->getMessage();
        }

        if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            return $e->getMessage() !== '' ? $e->getMessage() : self::GENERIC_ERROR;
        }

        $businessClasses = [\Exception::class, \RuntimeException::class, \InvalidArgumentException::class];

        if (in_array($e::class, $businessClasses, true) || str_starts_with($e::class, 'App\\')) {
            return $e->getMessage();
        }

        return self::GENERIC_ERROR;
    }
}
