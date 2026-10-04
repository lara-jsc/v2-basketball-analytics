<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterCoachRequest;
use App\Repositories\TeamRepository;
use App\Services\CoachRegistrationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly CoachRegistrationService $coachRegistrationService,
    ) {}

    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'claimableTeams' => $this->teamRepository->claimableForSignup(),
            'joinableTeams' => $this->teamRepository->joinableForSignup(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(RegisterCoachRequest $request): RedirectResponse
    {
        $user = $this->coachRegistrationService->register($request->validated());

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
