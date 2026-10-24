<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Api\TokenActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\EmptyRequest;
use App\Http\Requests\Api\IssueTokenRequest;
use App\Http\Requests\Api\PaginationRequest;
use App\Http\Resources\Api\TokenResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class TokenController extends Controller
{
    public function store(IssueTokenRequest $request, TokenActions $actions): JsonResponse
    {
        $token = $actions->issue($request->validated());

        return response()->json(['data' => [
            'token_type' => 'Bearer', 'access_token' => $token->plainTextToken,
            'token' => (new TokenResource($token->accessToken))->resolve($request),
        ]], 201);
    }

    public function index(PaginationRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PersonalAccessToken::class);

        return TokenResource::collection($request->user()->tokens()->latest('id')->paginate($request->pageSize())->appends($request->validated()));
    }

    public function destroy(EmptyRequest $request, int $token, TokenActions $actions): Response
    {
        $actions->revoke($request->user(), $request->user()->tokens()->findOrFail($token));

        return response()->noContent();
    }

    public function current(EmptyRequest $request, TokenActions $actions): Response
    {
        $actions->revoke($request->user(), $request->user()->currentAccessToken());

        return response()->noContent();
    }
}
