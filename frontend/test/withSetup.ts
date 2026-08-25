import { defineComponent } from 'vue';
import { mountSuspended } from '@nuxt/test-utils/runtime';

// Composables using lifecycle hooks (onMounted/onBeforeUnmount -- see
// useOnlineStatus.ts, useChatbot.ts) only work inside a component's setup(),
// not called bare in a test. mountSuspended (not plain @vue/test-utils
// mount()) is required here, not just for Suspense: it's also what actually
// installs the Nuxt app's plugins (vue-i18n's app.use(), etc.) onto the
// mounted component -- a bare mount() leaves useI18n() throwing "Need to
// install with `app.use` function". Call wrapper.unmount() to fire
// onBeforeUnmount.
export async function withSetup<T>(
  composable: () => T,
): Promise<[T, Awaited<ReturnType<typeof mountSuspended>>]> {
  let result!: T;
  const wrapper = await mountSuspended(
    defineComponent({
      setup() {
        result = composable();

        return () => null;
      },
    }),
  );

  return [result, wrapper];
}
