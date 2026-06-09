<?php

namespace App\Http\Controllers\API\V1\Auth;

use App\Actions\Auth\ForgotPasswordAction;
use App\Actions\Auth\GetMeAction;
use App\Actions\Auth\LoginAction;
use App\Actions\Auth\LogoutAction;
use App\Actions\Auth\RefreshTokenAction;
use App\Actions\Auth\ResendOtpAction;
use App\Actions\Auth\ResetPasswordAction;
use App\Actions\Auth\VerifyOtpAction;
use App\Actions\Auth\VerifyResetCodeAction;
use App\Actions\Salon\SalonRegisterAction;
use App\Data\ForgotPasswordData;
use App\Data\LoginData;
use App\Data\ResetPasswordData;
use App\Data\Salon\SalonRegisterData;
use App\Exceptions\PlanNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Salon\SalonRegisterRequest;
use App\Http\Requests\V1\User\ForgotPasswordRequest;
use App\Http\Requests\V1\User\LoginRequest;
use App\Http\Requests\V1\User\ResendOtpRequest;
use App\Http\Requests\V1\User\ResetPasswordRequest;
use App\Http\Requests\V1\User\VerifyOtpRequest;
use App\Http\Requests\V1\User\VerifyResetCodeRequest;
use App\Http\Resources\V1\Auth\AuthResource;
use App\Http\Resources\V1\Auth\MeResource;
use App\Http\Resources\V1\Salon\SalonRegisterResource;
use App\Services\Auth\JwtService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Authentification",
 *     description="Gestion de l'authentification et des sessions"
 * )
 */
class AuthController extends Controller
{
    public function __construct(
        private SalonRegisterAction $salonRegisterAction,
        private GetMeAction $getMeAction,
        private LoginAction $loginAction,
        private LogoutAction $logoutAction,
        private RefreshTokenAction $refreshTokenAction,
        private VerifyOtpAction $verifyOtpAction,
        private ResendOtpAction $resendOtpAction,
        private ForgotPasswordAction $forgotPasswordAction,
        private VerifyResetCodeAction $verifyResetCodeAction,
        private ResetPasswordAction $resetPasswordAction,
        // Désactivé : Actions de vérification par lien (non utilisé pour app mobile)
        // private VerifyEmailAction $verifyEmailAction,
        // private ResendVerificationEmailAction $resendVerificationEmailAction,
        private JwtService $jwtService,
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/auth/register",
     *     summary="Inscription salon et gérant",
     *     description="Crée un salon, son gérant admin et une subscription trial. Retourne un token JWT.",
     *     operationId="registerSalon",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Identifiants d'inscription",
     *         @OA\JsonContent(
     *             required={"login", "password", "password_confirmation"},
     *             @OA\Property(property="login", type="string", example="jean.dupont@example.com", description="Email ou numéro de téléphone"),
     *             @OA\Property(property="password", type="string", format="password", example="MotDePasse123!", minLength=8, description="Mot de passe (min 8 caractères, majuscule, minuscule, chiffre, symbole)"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="MotDePasse123!", description="Confirmation du mot de passe")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Code OTP envoyé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Code OTP envoyé par email. Veuillez vérifier pour finaliser votre inscription."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="email", type="string", nullable=true, example="jean.dupont@example.com"),
     *                 @OA\Property(property="phone", type="string", nullable=true, example=null),
     *                 @OA\Property(property="requires_otp_verification", type="boolean", example=true),
     *                 @OA\Property(property="expires_in", type="integer", example=10, description="Durée de validité de l'OTP en minutes")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Données invalides",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Les données fournies sont invalides.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cet email est déjà utilisé.")
     *         )
     *     )
     * )
     */
    public function register(SalonRegisterRequest $request): JsonResponse
    {
        try {
            $data = SalonRegisterData::fromRequest($request);
            $outcome = $this->salonRegisterAction->execute($data, $request->ip() ?? 'unknown');

            return new JsonResponse([
                'success' => true,
                'message' => 'Inscription réussie.',
                'data' => new SalonRegisterResource($outcome),
            ], Response::HTTP_CREATED);
        } catch (PlanNotFoundException $e) {
            Log::error('PlanNotFoundException: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la création du compte. Veuillez réessayer.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (Exception $e) {
            Log::error('Erreur lors de l\'inscription salon: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     summary="Connexion",
     *     description="Authentifie un utilisateur avec email/téléphone et mot de passe, retourne un token JWT",
     *     operationId="loginUser",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Identifiants de connexion",
     *         @OA\JsonContent(
     *             required={"login", "password"},
     *             @OA\Property(property="login", type="string", example="jean.dupont@example.com", description="Email ou numéro de téléphone"),
     *             @OA\Property(property="password", type="string", format="password", example="MotDePasse123", minLength=8)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connexion réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Connexion réussie"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     @OA\Property(property="id", type="string", format="uuid"),
     *                     @OA\Property(property="first_name", type="string"),
     *                     @OA\Property(property="last_name", type="string"),
     *                     @OA\Property(property="email", type="string"),
     *                     @OA\Property(property="phone", type="string"),
     *                     @OA\Property(property="is_active", type="boolean")
     *                 ),
     *                 @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(property="expires_in", type="integer", example=3600)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Identifiants invalides",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Identifiants invalides")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Données invalides",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Les données fournies sont invalides.")
     *         )
     *     )
     * )
     */
    public function login(LoginRequest $request)
    {
        try {
            $loginData = LoginData::fromRequest($request);

            $login = $this->loginAction->execute($loginData);

            return new JsonResponse([
                'success' => true,
                'message' => 'Connexion réussie',
                'data' => new AuthResource($login),
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la connexion: ' . $e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     summary="Déconnexion",
     *     description="Déconnecte l'utilisateur et invalide le token JWT",
     *     operationId="logoutUser",
     *     tags={"Authentification"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Déconnexion réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Déconnexion réussie")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non autorisé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Token invalide ou non fourni")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Erreur lors de la déconnexion")
     *         )
     *     )
     * )
     */
    public function logout()
    {
        try {
            $this->logoutAction->execute();

            return new JsonResponse([
                'success' => true,
                'message' => 'Déconnexion réussie',
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la déconnexion: ' . $e->getMessage());
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/auth/me",
     *     summary="Profil de l'utilisateur connecté",
     *     description="Retourne le gérant, son salon et la subscription active",
     *     operationId="getAuthenticatedUser",
     *     tags={"Authentification"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Profil récupéré avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Profil récupéré avec succès"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non autorisé",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Token non fourni ou invalide")
     *         )
     *     )
     * )
     */
    public function me(): JsonResponse
    {
        try {
            $me = $this->getMeAction->execute();

            return new JsonResponse([
                'success' => true,
                'message' => 'Profil récupéré avec succès',
                'data' => new MeResource($me),
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération du profil: '.$e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/refresh",
     *     summary="Rafraîchir le token JWT",
     *     description="Génère un nouveau token JWT à partir d'un token existant (valide ou expiré mais dans la fenêtre de refresh)",
     *     operationId="refreshToken",
     *     tags={"Authentification"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Token rafraîchi avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Token rafraîchi avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="access_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(property="expires_in", type="integer", example=3600)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Token invalide ou expiré",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Token invalide ou expiré")
     *         )
     *     )
     *     )
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Token non fourni',
                ], Response::HTTP_UNAUTHORIZED);
            }

            $newToken = $this->refreshTokenAction->execute($token);

            return new JsonResponse([
                'success' => true,
                'message' => 'Token rafraîchi avec succès',
                'data' => [
                    'access_token' => $newToken,
                    'token_type' => 'Bearer',
                    'expires_in' => $this->jwtService->getExpiresIn($newToken),
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Erreur lors du rafraîchissement du token: ' . $e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/forgot-password",
     *     summary="Demander la réinitialisation du mot de passe",
     *     description="Envoie un lien de réinitialisation du mot de passe par email",
     *     operationId="forgotPassword",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Email du compte",
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="jean.dupont@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lien envoyé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Un lien de réinitialisation a été envoyé à votre adresse email")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Données invalides",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Les données fournies sont invalides.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Impossible d'envoyer le lien de réinitialisation")
     *         )
     *     )
     * )
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $data = ForgotPasswordData::fromRequest($request);
            $result = $this->forgotPasswordAction->execute($data);

            return new JsonResponse([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'expires_in' => $result['expires_in'] ?? 10,
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'envoi du lien de réinitialisation du mot de passe: ' . $e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/verify-reset-code",
     *     summary="Vérifier le code de réinitialisation",
     *     description="Vérifie le code OTP reçu pour la réinitialisation de mot de passe",
     *     operationId="verifyResetCode",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "code"},
     *             @OA\Property(property="email", type="string", format="email", example="john.doe@example.com"),
     *             @OA\Property(property="code", type="string", example="123456", description="Code OTP à 6 chiffres")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Code vérifié avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Code vérifié. Vous pouvez maintenant changer votre mot de passe."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="token", type="string", example="abc123randomtoken"),
     *                 @OA\Property(property="expires_in", type="integer", example=15, description="Temps d'expiration en minutes")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Code invalide ou expiré",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Code incorrect")
     *         )
     *     )
     * )
     */
    public function verifyResetCode(VerifyResetCodeRequest $request): JsonResponse
    {
        try {
            $result = $this->verifyResetCodeAction->execute($request->email, $request->code);

            return new JsonResponse([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'token' => $result['token'],
                    'expires_in' => $result['expires_in'],
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error verifying reset code: ' . $e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/reset-password",
     *     summary="Réinitialiser le mot de passe",
     *     description="Réinitialise le mot de passe avec le token reçu après vérification du code",
     *     operationId="resetPassword",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Token et nouveau mot de passe",
     *         @OA\JsonContent(
     *             required={"token", "email", "password", "password_confirmation"},
     *             @OA\Property(property="token", type="string", example="abc123token"),
     *             @OA\Property(property="email", type="string", format="email", example="jean.dupont@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="NouveauMotDePasse123!", minLength=8),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="NouveauMotDePasse123!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Mot de passe réinitialisé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Votre mot de passe a été réinitialisé avec succès")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Données invalides ou token invalide",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Le token de réinitialisation est invalide ou a expiré")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Erreur serveur",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Impossible de réinitialiser le mot de passe")
     *         )
     *     )
     * )
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $data = ResetPasswordData::fromRequest($request);
            $result = $this->resetPasswordAction->execute($data);

            return new JsonResponse([
                'success' => true,
                'message' => $result['message'],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error resetting password: ' . $e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    // DÉSACTIVÉ : Vérification d'email par lien (non utilisé pour app mobile)
    // La vérification se fait uniquement via OTP lors de l'inscription
    // Méthodes verifyEmail() et resendVerification() supprimées

    /**
     * @OA\Post(
     *     path="/api/v1/auth/verify-otp",
     *     summary="Vérifier le code OTP",
     *     description="Vérifie le code OTP reçu par email ou SMS après inscription",
     *     operationId="verifyOtp",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"login", "code"},
     *             @OA\Property(property="login", type="string", example="john.doe@example.com", description="Email ou numéro de téléphone"),
     *             @OA\Property(property="code", type="string", example="123456", description="Code OTP à 6 chiffres")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Code OTP vérifié avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Code OTP vérifié avec succès"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/UserResource")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Code OTP invalide ou expiré",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Code OTP invalide")
     *         )
     *     )
     * )
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        try {
            $auth = $this->verifyOtpAction->execute($request->login, $request->code);

            return new JsonResponse([
                'success' => true,
                'message' => 'Code OTP véri fié avec succès, authentification réussie',
                'data' => new AuthResource($auth),
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error verifying OTP: ' . $e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/resend-otp",
     *     summary="Renvoyer le code OTP",
     *     description="Renvoie un nouveau code OTP par email ou SMS",
     *     operationId="resendOtp",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"login"},
     *             @OA\Property(property="login", type="string", example="john.doe@example.com", description="Email ou numéro de téléphone")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Code OTP renvoyé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Un nouveau code OTP a été envoyé")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Erreur lors de l'envoi du code OTP",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Utilisateur non trouvé")
     *         )
     *     )
     * )
     */
    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        try {
            $result = $this->resendOtpAction->execute($request->login);

            return new JsonResponse([
                'success' => true,
                'message' => 'Un nouveau code OTP a été envoyé par ' .
                    ($result['email'] && $result['phone'] ? 'email et SMS' :
                    ($result['email'] ? 'email' : 'SMS')),
                'data' => [
                    'channel' => $result['channel'],
                    'login' => $result['login'],
                    'requires_otp_verification' => true,
                    'expires_in' => $result['expires_in'],
                    'email' => $result['email'],
                    'phone' => $result['phone'],
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Error resending OTP: ' . $e->getMessage());

            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

}
