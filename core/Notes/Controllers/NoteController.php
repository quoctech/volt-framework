<?php

declare(strict_types=1);

namespace Volt\Core\Notes\Controllers;

use CodeIgniter\Controller;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Notes\Models\NoteModel;

class NoteController extends Controller
{
    private NoteModel $noteModel;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        helper(['form', 'url']);
        $this->noteModel = new NoteModel();
    }

    public function index()
    {
        $notes = $this->noteModel
            ->orderBy('id', 'DESC')
            ->findAll();

        return view('notes/index', [
            'notes' => $notes,
        ]);
    }

    public function create()
    {
        return view('notes/form', [
            'note' => null,
            'errors' => [],
        ]);
    }

    public function store()
    {
        $rules = [
            'title' => 'required|min_length[3]|max_length[255]',
            'body' => 'permit_empty|max_length[5000]',
            'status' => 'required|in_list[draft,published,archived]',
        ];

        if (! $this->validate($rules)) {
            return view('notes/form', [
                'note' => null,
                'errors' => $this->validator->getErrors(),
            ]);
        }

        $actor = service('voltAuth')->currentUser();

        $this->noteModel->setActor($actor instanceof UserEntity ? $actor : null)->insert([
            'title' => trim((string) $this->request->getPost('title')),
            'body' => trim((string) $this->request->getPost('body')),
            'status' => (string) $this->request->getPost('status'),
            'owner' => $actor?->name ?? 'system',
        ]);

        return redirect()->to(site_url('notes'));
    }

    public function edit(int $id)
    {
        $note = $this->noteModel->find($id);

        if ($note === null) {
            return redirect()->to(site_url('notes'));
        }

        return view('notes/form', [
            'note' => $note,
            'errors' => [],
        ]);
    }

    public function update(int $id)
    {
        $rules = [
            'title' => 'required|min_length[3]|max_length[255]',
            'body' => 'permit_empty|max_length[5000]',
            'status' => 'required|in_list[draft,published,archived]',
        ];

        if (! $this->validate($rules)) {
            $note = $this->noteModel->find($id);

            return view('notes/form', [
                'note' => $note,
                'errors' => $this->validator->getErrors(),
            ]);
        }

        $this->noteModel->setActor(service('voltAuth')->currentUser())
            ->update($id, [
                'title' => trim((string) $this->request->getPost('title')),
                'body' => trim((string) $this->request->getPost('body')),
                'status' => (string) $this->request->getPost('status'),
            ]);

        return redirect()->to(site_url('notes'));
    }

    public function delete(int $id)
    {
        $this->noteModel->setActor(service('voltAuth')->currentUser())->delete($id);

        return redirect()->to(site_url('notes'));
    }
}
