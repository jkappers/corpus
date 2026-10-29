<?php

namespace App\Mcp\Tools;

use App\Exceptions\StaleWriteException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Runs a tool as the OAuth user and turns authorization, lookup and validation
 * failures into tool errors the model can read, instead of protocol errors.
 */
abstract class CorpusTool extends Tool
{
    abstract protected function execute(Request $request, User $user): Response;

    public function handle(Request $request): Response
    {
        try {
            return $this->execute($request, $request->user());
        } catch (AuthorizationException) {
            return Response::error('You do not have permission to do that.');
        } catch (ModelNotFoundException) {
            return Response::error('Not found.');
        } catch (StaleWriteException $exception) {
            return Response::error($exception->getMessage().' Read it again and retry.');
        } catch (ValidationException $exception) {
            return Response::error($exception->validator->errors()->first());
        }
    }
}
