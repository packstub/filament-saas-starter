<?php

// The Activity page (the workspace's audit log) and the sentences its entries are written in.
return [

    'title' => 'Activity',

    'events' => [
        'member_added' => 'Member added',
        'member_removed' => 'Member removed',
        'role_changed' => 'Role changed',
        'invitation_sent' => 'Invitation sent',
        'invitation_accepted' => 'Invitation accepted',
        'ownership_transferred' => 'Ownership transferred',
        'plan_changed' => 'Plan changed',
        'api_token_created' => 'API token created',
        'api_token_revoked' => 'API token revoked',
        'workspace_deleted' => 'Workspace deleted',
    ],

    'columns' => [
        'event' => 'Event',
        'description' => 'Description',
        'by' => 'By',
        'when' => 'When',
    ],

    'empty' => 'Nothing has happened yet',

    'system' => 'System',

    'actor_with_support' => ':actor (support: :support)',

    'descriptions' => [
        'member_added' => ':name joined as :role',
        'member_removed' => ':name is no longer a member',
        'role_changed' => ':name changed from :from to :to',
        'invitation_sent_email' => 'Invited :email as :role',
        'invitation_sent_link' => 'Created an invitation link for :role',
        'invitation_accepted' => ':name accepted an invitation',
        'ownership_transferred' => 'Ownership moved from :from to :to',
        'api_token_created' => 'Created the API token :name (:abilities)',
        'api_token_revoked' => 'Revoked the API token :name',
        'api_token_revoked_from' => 'Revoked the API token :name (:from no longer manages the workspace)',
        'plan_changed' => 'Plan changed from :from to :to',
        'workspace_deleted' => 'Deleted the workspace :workspace',
    ],

];
