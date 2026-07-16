<?php

namespace App\Livewire\Cards\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * File attachments and the card cover (image or colour).
 *
 * Extracted from the CardDetail god-class; expects the consuming component
 * to expose $board, $cardId and the guardedCard()/logActivity()/touched()
 * helpers (see App\Livewire\Cards\CardDetail).
 */
trait ManagesAttachments
{
    public mixed $upload = null;

    public mixed $coverUpload = null;

    public function saveAttachment(): void
    {
        $card = $this->guardedCard();

        $this->validate([
            'upload' => ['required', 'file', 'max:204800', 'mimes:jpg,jpeg,png,gif,webp,svg,mp4,webm,mov,ogg'],
        ]);

        if (! $this->board->workspace->attachmentExtensionAllowed($this->upload->getClientOriginalExtension())) {
            $this->addError('upload', __("Ce type de fichier n'est pas autorisé pour ce workspace."));

            return;
        }

        // Read metadata BEFORE store(): the attachments disk is now the same as
        // Livewire's temporary-upload disk, so store() moves (not copies) the temp
        // file — reading getSize()/getMimeType() afterwards would fail.
        $name = $this->upload->getClientOriginalName();
        $mimeType = $this->upload->getMimeType();
        $size = $this->upload->getSize();

        $path = $this->upload->store("attachments/{$this->board->id}", 'local');

        $card->attachments()->create([
            'uploaded_by' => Auth::id(),
            'disk' => 'local',
            'path' => $path,
            'name' => $name,
            'mime_type' => $mimeType,
            'size' => $size,
        ]);

        $this->logActivity($card, 'attachment.added', ['value' => $name]);
        $this->reset('upload');
        $this->touched('attachment.added');
        $this->dispatch('toast', message: __('Pièce jointe ajoutée'), type: 'success');
    }

    public function deleteAttachment(int $attachmentId): void
    {
        $card = $this->guardedCard();
        $attachment = $card->attachments()->findOrFail($attachmentId);

        Storage::disk($attachment->disk)->delete($attachment->path);

        if ($card->cover_path === $attachment->path) {
            $card->update(['cover_path' => null]);
        }

        $attachment->delete();
        $this->touched('attachment.deleted');
    }

    public function setCover(int $attachmentId): void
    {
        $card = $this->guardedCard();
        $attachment = $card->attachments()->findOrFail($attachmentId);

        if (! $attachment->isImage()) {
            return;
        }

        $useAsCover = $card->cover_path !== $attachment->path;

        $card->update([
            'cover_path' => $useAsCover ? $attachment->path : null,
            'cover_color' => $useAsCover ? null : $card->cover_color,
        ]);
        $this->touched('card.cover');
    }

    public function setCoverColor(string $color): void
    {
        $card = $this->guardedCard();

        $card->update([
            'cover_color' => $card->cover_color === $color ? null : $color,
            'cover_path' => null,
        ]);
        $this->touched('card.cover');
    }

    public function clearCover(): void
    {
        $card = $this->guardedCard();

        $card->update(['cover_path' => null, 'cover_color' => null]);
        $this->touched('card.cover');
    }

    public function uploadCover(): void
    {
        $card = $this->guardedCard();

        $this->validate(['coverUpload' => ['required', 'image', 'max:10240']]);

        $path = $this->coverUpload->store("covers/{$this->board->id}", 'local');

        $card->update(['cover_path' => $path, 'cover_color' => null]);
        $this->reset('coverUpload');
        $this->touched('card.cover');
        $this->dispatch('toast', message: __('Couverture mise à jour'), type: 'success');
    }
}
