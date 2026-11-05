<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Services\Webhooks\Interfaces\WebhookVerifierInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Clerk tells us when something happens to an account. For now that is one thing: `user.deleted`, which removes the user
 * and their data so their reminders stop. Clerk retries anything that is not answered with a 2xx, so an event we have no use
 * for, or a user we no longer have, is still a success.
 */
final class ClerkController extends Controller
{
    public function __invoke(Request $request, LoggerInterface $logger, UserRepositoryInterface $users, WebhookVerifierInterface $verifier): JsonResponse|Response
    {
        $payload = $request->getContent();

        $signed = $verifier->verify($payload, [
            'svix-id' => $request->header('svix-id'),
            'svix-signature' => $request->header('svix-signature'),
            'svix-timestamp' => $request->header('svix-timestamp'),
        ]);

        if ($signed === false) {
            return new JsonResponse(['message' => 'The signature is not valid.'], SymfonyResponse::HTTP_UNAUTHORIZED);
        }

        $event = \json_decode($payload, true);
        $type = \is_array($event) ? ($event['type'] ?? null) : null;

        if (\is_string($type) === false) {
            return new JsonResponse(['message' => 'That is not an event.'], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        $userId = \is_array($event['data'] ?? null) ? ($event['data']['id'] ?? null) : null;

        if ($type === 'user.deleted' && \is_string($userId) && $userId !== '') {
            $deleted = $users->deleteUserByExternalId($userId);

            $logger->info('Clerk user deleted.', ['deleted_here' => $deleted, 'user' => $userId]);
        }

        return new Response(status: SymfonyResponse::HTTP_NO_CONTENT);
    }
}
