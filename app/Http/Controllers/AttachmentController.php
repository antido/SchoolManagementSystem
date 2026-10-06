<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\AttachmentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Download a file, if the person may see the record it is attached to.
     * Images can be asked for with ?inline=1 to show them in a page.
     */
    public function show(Request $request, Attachment $attachment, AttachmentService $attachments): StreamedResponse
    {
        $this->authorize('view', $attachment);

        return $attachments->download($attachment, inline: $request->boolean('inline'));
    }
}
