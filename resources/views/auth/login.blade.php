<x-layout title="Sign in">
    <main class="auth-shell">
        <section class="auth-story">
            <a class="brand" href="{{ url('/') }}"><span class="brand-mark">g.</span> Gchat</a>
            <div>
                <span class="eyebrow">A LITTLE LESS NOISE</span>
                <h1>Good conversations.<br>A little more presence.</h1>
                <p>A private space for the two of you. Be in the moment, or take your time with the full conversation.</p>
                <div class="mode-preview"><span class="mode-dot"></span> In the moment <span class="preview-divider">/</span> Saved for later</div>
            </div>
            <span class="story-footnote">Two people. Two modes. One conversation.</span>
        </section>
        <section class="auth-panel">
            <div class="auth-card">
                <span class="eyebrow">WELCOME BACK</span>
                <h2>Pick up the conversation.</h2>
                <p class="muted">Sign in to your private messages.</p>
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
