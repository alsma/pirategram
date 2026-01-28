import { configureEcho } from '@laravel/echo-react'

export { onBeforeRender }

async function onBeforeRender(pageContext) {
  configureEcho({
    broadcaster: "ably",
    key: import.meta.env.PUBLIC_ENV__ABLY_PUBLIC_KEY,
    wsHost: "realtime-pusher.ably.io",
    wsPort: 443,
    disableStats: true,
    encrypted: true,
    namespace: false,
  });
}