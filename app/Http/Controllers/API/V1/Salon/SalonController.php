<?php

namespace App\Http\Controllers\API\V1\Salon;

use App\Actions\Salon\GetSalonAction;
use App\Actions\Salon\UpdateSalonAction;
use App\Actions\Salon\UploadSalonLogoAction;
use App\Data\Salon\UpdateSalonData;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Salon\UpdateSalonRequest;
use App\Http\Requests\V1\Salon\UploadSalonLogoRequest;
use App\Http\Resources\V1\Salon\SalonDetailResource;
use App\Services\PublicLinkService;
use App\Services\Salon\SalonBookingQrCodeService;
use App\Services\Salon\SalonContextService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Tag(
 *     name="Salon",
 *     description="Gestion du profil salon"
 * )
 */
class SalonController extends Controller
{
    public function __construct(
        private GetSalonAction $getSalonAction,
        private UpdateSalonAction $updateSalonAction,
        private UploadSalonLogoAction $uploadSalonLogoAction,
        private SalonContextService $salonContext,
        private SalonBookingQrCodeService $bookingQrCodeService,
        private PublicLinkService $publicLinkService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/salon",
     *     summary="Informations complètes du salon connecté",
     *     operationId="getSalon",
     *     tags={"Salon"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Salon récupéré avec succès",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Salon récupéré avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Non authentifié"),
     *     @OA\Response(response=403, description="Accès réservé aux administrateurs")
     * )
     */
    public function show(): JsonResponse
    {
        try {
            $detail = $this->getSalonAction->execute();

            return new JsonResponse([
                'success' => true,
                'message' => 'Salon récupéré avec succès',
                'data' => new SalonDetailResource($detail),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur récupération salon: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/salon",
     *     summary="Modifier le profil du salon",
     *     operationId="updateSalon",
     *     tags={"Salon"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string", example="Salon Koffi Cocody"),
     *             @OA\Property(property="phone", type="string", example="2250708112233"),
     *             @OA\Property(property="whatsapp_number", type="string", example="2250708112233"),
     *             @OA\Property(property="city", type="string", example="Abidjan"),
     *             @OA\Property(property="address", type="string", example="Cocody, Rue des jardins")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Salon mis à jour avec succès"),
     *     @OA\Response(response=422, description="Données invalides"),
     *     @OA\Response(response=403, description="Accès réservé aux administrateurs")
     * )
     */
    public function update(UpdateSalonRequest $request): JsonResponse
    {
        try {
            $detail = $this->updateSalonAction->execute(UpdateSalonData::fromRequest($request));

            return new JsonResponse([
                'success' => true,
                'message' => 'Salon mis à jour avec succès',
                'data' => new SalonDetailResource($detail),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur mise à jour salon: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/salon/logo",
     *     summary="Upload du logo du salon",
     *     operationId="uploadSalonLogo",
     *     tags={"Salon"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *                 required={"logo"},
     *
     *                 @OA\Property(property="logo", type="string", format="binary")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Logo uploadé avec succès"),
     *     @OA\Response(response=422, description="Fichier invalide"),
     *     @OA\Response(response=403, description="Accès réservé aux administrateurs")
     * )
     */
    /**
     * @OA\Get(
     *     path="/api/v1/salon/booking-qr",
     *     summary="QR code PNG de réservation du salon",
     *     description="Encode le même lien que booking_link. Nécessite l'extension PHP imagick.",
     *     operationId="getSalonBookingQrCode",
     *     tags={"Salon"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Image PNG",
     *
     *         @OA\MediaType(
     *             mediaType="image/png",
     *
     *             @OA\Schema(type="string", format="binary")
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Accès réservé aux administrateurs")
     * )
     */
    public function bookingQr(): Response
    {
        try {
            $salon = $this->salonContext->resolveAuthenticatedSalon();
            $png = $this->bookingQrCodeService->generatePng($salon->slug);

            return new Response($png, Response::HTTP_OK, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'inline; filename="booking-qr-'.$salon->slug.'.png"',
            ]);
        } catch (Exception $e) {
            Log::error('Erreur génération QR salon: '.$e->getMessage());

            return new Response($this->safeMessage($e), Response::HTTP_INTERNAL_SERVER_ERROR, [
                'Content-Type' => 'text/plain',
            ]);
        }
    }

    /**
     * QR code d'arrivée à afficher à l'accueil : les clients le scannent en arrivant pour
     * rejoindre la file. La clé est créée au premier appel.
     */
    public function checkinQr(): JsonResponse
    {
        return $this->checkinQrResponse(regenerate: false);
    }

    /** Nouvelle clé d'arrivée : l'ancien QR (photo partagée, affiche volée) cesse de fonctionner. */
    public function regenerateCheckinKey(): JsonResponse
    {
        return $this->checkinQrResponse(regenerate: true);
    }

    private function checkinQrResponse(bool $regenerate): JsonResponse
    {
        try {
            $salon = $this->salonContext->resolveAuthenticatedSalon();

            if ($regenerate || $salon->checkin_key === null) {
                $salon->forceFill(['checkin_key' => Str::random(32)])->save();
            }

            $url = $this->publicLinkService->buildCheckinLink($salon->slug, $salon->checkin_key);

            return new JsonResponse([
                'success' => true,
                'message' => $regenerate ? 'Nouveau QR code d\'arrivée généré.' : 'QR code d\'arrivée récupéré.',
                'data' => [
                    'checkin_url' => $url,
                    'qr_code' => $this->bookingQrCodeService->dataUriForUrl($url),
                    'late_tolerance_minutes' => $salon->late_tolerance_minutes ?? 15,
                ],
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur QR d\'arrivée: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function uploadLogo(UploadSalonLogoRequest $request): JsonResponse
    {
        try {
            $detail = $this->uploadSalonLogoAction->execute($request->file('logo'));

            return new JsonResponse([
                'success' => true,
                'message' => 'Logo uploadé avec succès',
                'data' => new SalonDetailResource($detail),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erreur upload logo salon: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $this->safeMessage($e),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
