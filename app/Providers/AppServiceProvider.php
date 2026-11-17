<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspacePermissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One per request or queued job, never shared between two of them.
        $this->app->scoped(WorkspacePermissions::class);
    }

    public function boot(): void
    {
        // Subscriptions belong to the workspace: one bill however many members it has.
        Cashier::useCustomerModel(Workspace::class);

        // Short names instead of class names in polymorphic columns (notifications,
        // feature overrides), so a model can be renamed or moved without touching data.
        Relation::morphMap([
            'user' => User::class,
            'workspace' => Workspace::class,
            'project' => Project::class,
        ]);

        // The tables a workspace owns, kept apart from accounts, memberships and billing.
        $this->loadMigrationsFrom(database_path('migrations/workspace'));

        // While developing and testing, a lazy-loaded relation (an N+1 query), an attribute
        // that was not selected and a field left out of the model's fillable list are errors
        // instead of silent surprises. Production stays lenient.
        Model::shouldBeStrict(! $this->app->isProduction());

        // What `Password::defaults()` means wherever a password is chosen: the sign-up,
        // password reset and profile pages. Longer in production, and checked against
        // known data leaks; eight characters elsewhere, so the demo accounts can keep `password`.
        Password::defaults(fn (): Password => $this->app->isProduction()
            ? Password::min(12)->uncompromised()
            : Password::min(8));
    }
}
