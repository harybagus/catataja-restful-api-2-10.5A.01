<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\NoteCreateRequest;
use App\Http\Requests\NoteUpdateRequest;
use App\Http\Resources\NoteCollection;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class NoteController extends Controller
{
    private function getNoteById(int $id, User $user)
    {
        $note = Note::where("id", $id)->where("user_id", $user->id)->first();
        if (!$note) {
            throw new HttpResponseException(response()->json([
                "errors" => [
                    "message" => [
                        "No note found."
                    ]
                ]
            ])->setStatusCode(404));
        }

        return $note;
    }

    public function create(NoteCreateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = Auth::user();

        $note = new Note($data);
        $note->user_id = $user->id;
        $note->save();

        return (new NoteResource($note))->response()->setStatusCode(201);
    }

    public function get(Request $request): JsonResponse
    {
        $user = Auth::user();

        $pinnedNotes = Note::where("pinned", "true")
            ->where("user_id", $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $unPinnedNotes = Note::where("pinned", "false")
            ->where("user_id", $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($pinnedNotes->isEmpty() && $unPinnedNotes->isEmpty()) {
            throw new HttpResponseException(response()->json([
                "errors" => [
                    "message" => [
                        "No notes found."
                    ]
                ]
            ])->setStatusCode(404));
        }

        return response()->json([
            "pinned" => NoteResource::collection($pinnedNotes),
            "unpinned" => NoteResource::collection($unPinnedNotes),
        ], 200);
    }

    public function search(Request $request): NoteCollection
    {
        $user = Auth::user();

        $query = Note::where('user_id', $user->id);

        if ($keyword = $request->input('keyword')) {
            $query->where(function ($query) use ($keyword) {
                $query->where('title', 'like', '%' . $keyword . '%')
                    ->orWhere('description', 'like', '%' . $keyword . '%');
            });
        }

        $notes = $query->orderBy('created_at', 'desc')->get();

        if ($notes->isEmpty()) {
            throw new HttpResponseException(response()->json([
                "errors" => [
                    "message" => [
                        "No notes found."
                    ]
                ]
            ])->setStatusCode(404));
        }

        return new NoteCollection($notes);
    }

    public function update(int $id, NoteUpdateRequest $request): NoteResource
    {
        $user = Auth::user();

        $note = $this->getNoteById($id, $user);

        $data = $request->validated();
        $note->fill($data);
        $note->save();

        return new NoteResource($note);
    }

    public function delete(int $id): JsonResponse
    {
        $user = Auth::user();
        $note = $this->getNoteById($id, $user);
        $note->delete();

        return response()->json([
            "data" => true
        ])->setStatusCode(200);
    }
}
