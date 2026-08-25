// Gates dev/admin-only UI (token_usage on assistant messages, for now) --
// this widget has no real user-level auth on the frontend (the Nuxt proxy
// always authenticates as the same admin service account, see
// server/api/[...path].ts), so there's no session to check `is_admin`
// against. A `?debug=1` query param is the lightest gate that doesn't
// require building actual frontend auth for a single debug toggle.
export const useDebugMode = () => {
  const route = useRoute();

  return computed(() => '1' === route.query.debug);
};
