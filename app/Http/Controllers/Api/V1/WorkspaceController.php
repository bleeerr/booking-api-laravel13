<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreWorkspaceRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceRequest;
use App\Http\Resources\WorkspaceCollection;
use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceController extends Controller
{
    public function index(Request $request): WorkspaceCollection
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 20;

        $workspaces = Workspace::query()
            ->orderBy('id')
            ->paginate($perPage);

        return new WorkspaceCollection($workspaces);
    }

    public function show(Workspace $workspace): WorkspaceResource
    {
        return new WorkspaceResource($workspace);
    }

    public function store(StoreWorkspaceRequest $request): JsonResponse
    {
        Gate::authorize('create', Workspace::class);

        $workspace = Workspace::query()->create(
            $request->validated()
        );

        return (new WorkspaceResource($workspace))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        UpdateWorkspaceRequest $request,
        Workspace $workspace
    ): WorkspaceResource {
        Gate::authorize('update', $workspace);

        $workspace->update($request->validated());

        return new WorkspaceResource($workspace);
    }

    public function destroy(Workspace $workspace): Response
    {
        Gate::authorize('delete', $workspace);

        if ($workspace->rooms()->exists()) {
            throw new ApiException(
                errorCode: ApiErrorCode::ResourceHasDependencies,
                message: 'Workspace has rooms and cannot be deleted',
                httpStatus: Response::HTTP_CONFLICT,
            );
        }

        $workspace->delete();

        return response()->noContent();
    }
}
