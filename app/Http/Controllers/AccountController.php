<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Accounts/Index', [
            'accounts' => $this->accountService->listForIndex(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Accounts/Create', [
            'teams' => $this->accountService->teamOptions(),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $user = $this->accountService->create($request->validated());

        return redirect()
            ->route('accounts.index')
            ->with('success', "Account for {$user->name} created.");
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Accounts/Edit', [
            'account' => $this->accountService->summaryFor($user),
            'teams' => $this->accountService->teamOptions(),
        ]);
    }

    public function update(UpdateAccountRequest $request, User $user): RedirectResponse
    {
        $this->accountService->update($user, $request->validated());

        return redirect()
            ->route('accounts.index')
            ->with('success', "Account for {$user->name} updated.");
    }
}
