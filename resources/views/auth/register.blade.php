<x-layout title="Create account">
    <main class="auth-shell">
        <section class="auth-story">
            <a class="brand" href="{{ url('/') }}"><span class="brand-mark">g.</span> Gchat</a>
            <div>
                <span class="eyebrow">MAKE ROOM FOR CONNECTION</span>
                <h1>A conversation<br>just for the two of you.</h1>
                <p>Start with an email address. Stay in the moment with disappearing messages, or revisit everything in saved mode.</p>
                <div class="mode-preview"><span class="mode-dot"></span> Private, one to one</div>
            </div>
            <span class="story-footnote">Messages stay saved. You choose what you see.</span>
        </section>
        <section class="auth-panel">
            <div class="auth-card">
                <span class="eyebrow">YOUR SPACE STARTS HERE</span>
                <h2>Let’s get you settled.</h2>
                <p class="muted">Create an account to start a private conversation.</p>
                <form method="POST" action="{{ route('register') }}" class="stack">
                    @csrf
                    <div><label for="name">Your name</label><input id="name" name="name" autocomplete="name" value="{{ old('name') }}" required maxlength="80" autofocus>@error('name')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required>@error('email')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label for="password">Password <span class="muted">(12+ characters)</span></label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>@error('password')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
                    <button class="button primary" type="submit">Create account <span aria-hidden="true">↗</span></button>
                </form>
                <p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
            </div>
        </section>
    </main>
</x-layout>
