<?php

namespace App\Http\Controllers;

use App\Actions\StreamAttachment;
use App\Models\Attachment;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AttachmentController extends Controller
{
    public function show(Request $request, Attachment $attachment, StreamAttachment $stream): Response
    {
        return $stream->handle($attachment, $request->user());
    }
}
