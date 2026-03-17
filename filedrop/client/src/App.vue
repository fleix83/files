<template>
  <div class="app">
    <header class="header">
      <router-link to="/" class="logo">
        <img src="/assets/logo_wegweiser.svg" alt="GGG Wegweiser" class="logo-img" />
        <span class="logo-text">FILES</span>
      </router-link>
    </header>
    <main class="main">
      <router-view />
    </main>
  </div>
</template>

<style>
*,
*::before,
*::after {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  background: #f8f9fa;
  color: #1a1a1a;
  line-height: 1.5;
  min-height: 100vh;
}

.app {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.header {
  background: #fff;
  border-bottom: 1px solid #e5e7eb;
  padding: 0.75rem 1.5rem;
}

.logo {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
}

.logo-img {
  height: 28px;
  width: auto;
}

.logo-text {
  font-size: 1.25rem;
  font-weight: 700;
  color: #729cd8;
  letter-spacing: 0.05em;
}

.main {
  flex: 1;
  max-width: 720px;
  width: 100%;
  margin: 0 auto;
  padding: 2rem 1rem;
}

button {
  cursor: pointer;
  font-family: inherit;
  font-size: 0.875rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 6px;
  font-weight: 500;
  transition: background 0.15s;
}

.btn-primary {
  background: #729cd8;
  color: #fff;
}

.btn-primary:hover {
  background: #5a88c4;
}

.btn-secondary {
  background: #e5e7eb;
  color: #374151;
}

.btn-secondary:hover {
  background: #d1d5db;
}

.btn-danger {
  background: #fee2e2;
  color: #dc2626;
}

.btn-danger:hover {
  background: #fecaca;
}

.btn-sm {
  padding: 0.25rem 0.625rem;
  font-size: 0.8125rem;
}
</style>
