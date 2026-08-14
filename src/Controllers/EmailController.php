<?php
declare(strict_types=1);

namespace SLC\Controllers;

use SLC\Core\Response;
use SLC\Core\Validator;
use SLC\Repositories\EmailRepository;

/**
 * Email templates + messages. Messages are DRAFT ONLY — no send action exists.
 */
class EmailController extends Controller
{
    public function __construct(private EmailRepository $emails = new EmailRepository())
    {
    }

    // ---- Templates ----
    public function templates(): void
    {
        Response::success(['templates' => $this->emails->allTemplates()]);
    }

    public function storeTemplate(): void
    {
        $data = $this->input();
        $v = new Validator($data);
        $v->required('name')->maxLength('name', 150);
        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }
        $id = $this->emails->createTemplate($data);
        $this->activity('email_template', 'Created email template: ' . $data['name']);
        Response::success(['template' => $this->emails->findTemplate($id)], 201);
    }

    public function deleteTemplate(string $id): void
    {
        $this->emails->deleteTemplate((int) $id);
        Response::success(['deleted' => true]);
    }

    // ---- Messages (draft only) ----
    public function messages(): void
    {
        Response::success(['messages' => $this->emails->allMessages($this->query())]);
    }

    public function storeMessage(): void
    {
        $data = $this->input();
        $v = new Validator($data);
        $v->required('subject');
        if ($v->fails()) {
            Response::validationError($v->errors());
            return;
        }
        $id = $this->emails->createMessage($data);
        $this->activity('email_draft', 'Saved email draft: ' . $data['subject'], (int) ($data['company_id'] ?? 0));
        Response::success(['message' => $this->emails->findMessage($id)], 201);
    }

    public function updateMessage(string $id): void
    {
        $data = $this->input();
        if (!$this->emails->findMessage((int) $id)) {
            Response::notFound('Email not found.');
            return;
        }
        $this->emails->updateMessage((int) $id, $data);
        Response::success(['message' => $this->emails->findMessage((int) $id)]);
    }

    public function deleteMessage(string $id): void
    {
        $this->emails->deleteMessage((int) $id);
        Response::success(['deleted' => true]);
    }
}
