export function xsrfToken() {
    const cookie = document.cookie
        .split('; ')
        .find((value) => value.startsWith('XSRF-TOKEN='));

    return decodeURIComponent(cookie?.split('=')[1] ?? '');
}
