<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Actions\Admin\AdminMeAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\AdminMeResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdminMeController extends Controller
{
    public function __construct(
        private AdminMeAction $adminMeAction,
    ) {}

    public function me(): JsonResponse
    {
        try {
            $me = $this->adminMeAction->execute();

            return new JsonResponse([
                'success' => true,
                'message' => 'Profil administrateur récupéré avec succès',
                'data' => new AdminMeResource($me),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur récupération profil admin: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
