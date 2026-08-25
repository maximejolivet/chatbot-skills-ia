// Tracks browser connectivity (`navigator.onLine` + the `online`/`offline`
// window events) so the chat widget can refuse to fire a doomed request
// instead of surfacing a confusing generic "erreur lors de l'envoi" for what
// is actually just no network at all -- see useChatbot.ts::sendMessage.
export const useOnlineStatus = () => {
  const isOnline = ref(true);

  const update = () => {
    isOnline.value = navigator.onLine;
  };

  onMounted(() => {
    update();
    window.addEventListener('online', update);
    window.addEventListener('offline', update);
  });

  onBeforeUnmount(() => {
    window.removeEventListener('online', update);
    window.removeEventListener('offline', update);
  });

  return computed(() => isOnline.value);
};
