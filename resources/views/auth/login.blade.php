<x-layout title="Sign in">
    <main class="auth-shell">
        <section class="auth-story">
            <a class="brand" href="{{ url('/') }}"><span class="brand-mark">e.</span> espace</a>
            <div>
                <span class="eyebrow">A LITTLE LESS NOISE</span>
                <h1>Good company.<br>A little more play.</h1>
                <p>A quick catch-up, a friendly game, and a little time well spent. Make room for a good little break.</p>
                <div class="mode-preview"><span class="mode-dot"></span> Chess <span class="preview-divider">/</span> Ludo <span class="preview-divider">/</span> Catch up</div>
            </div>
            <span class="story-footnote">Good company. Good moves.</span>
        </section>
        <section class="auth-panel">
            <div class="auth-card">
                <span class="eyebrow">WELCOME BACK</span>
                <h2>Pick up where you left off.</h2>
                <p class="muted">Your people and your next move are waiting.</p>
                <form method="POST" action="{{ route('login') }}" class="stack">
                    @csrf
                    <div><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required autofocus>@error('email')<p class="error">{{ $message }}</p>@enderror</div>
                    <div><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required>@error('password')<p class="error">{{ $message }}</p>@enderror</div>
                    <button class="button primary" type="submit">Sign in <span aria-hidden="true">↗</span></button>
                </form>
                <p class="auth-switch">New here? <a href="{{ route('register') }}">Create an account</a></p>
            </div>
        </section>
    </main>
</x-layout>
