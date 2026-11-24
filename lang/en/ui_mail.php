<?php

// The e-mails the app sends itself, and the line under every e-mail about a workspace.
return [

    'sent_by' => 'Sent by :app for :workspace.',

    'payment_failed' => [
        'subject' => 'We could not collect the payment for :workspace',
        'heading' => 'Your payment did not go through',
        'body' => 'Stripe could not collect the latest payment for **:workspace**. It will try again over the next few days, and the workspace keeps its plan in the meantime.',
        'update_card' => 'Update the card on the Billing page to settle it now.',
        'button' => 'Open Billing',
        'reason' => 'You receive this as an owner of :workspace.',
        'database_title' => 'The payment for :workspace failed',
        'database_body' => 'Update the card on the Billing page to keep your plan.',
        'database_action' => 'Open Billing',
    ],

    'workspace_deleted' => [
        'subject' => ':workspace was deleted',
        'heading' => ':workspace was deleted',
        'deleted_by' => ':person deleted the workspace **:workspace**.',
        'deleted' => 'The workspace **:workspace** was deleted.',
        'gone' => 'Its projects, members and invitations are gone, and its subscription was cancelled.',
        'unaffected' => 'Your account and your other workspaces are not affected.',
        'button' => 'Open :app',
    ],

    'account_deleted' => [
        'subject' => 'Your :app account was deleted',
        'heading' => 'Your account was deleted',
        'greeting' => 'Hello :name,',
        'body' => 'your :app account was deleted, together with every workspace where you were the only member. You left the other workspaces you were in, and their owners keep them.',
        'contact' => 'If you did not delete it yourself, reply to this e-mail or write to :email.',
    ],

];
