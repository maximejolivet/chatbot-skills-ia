<template>
  <a
    v-if="!failed && (isImage || preview?.title || loading)"
    :href="url"
    target="_blank"
    rel="noopener noreferrer"
    :class="[
      'mt-2 block max-w-xs overflow-hidden rounded-xl border border-border bg-background/60 transition-colors hover:bg-background',
      isImage ? '' : 'flex items-center gap-2.5 px-3 py-2',
    ]"
  >
    <template v-if="isImage">
      <div v-if="loading" class="h-40 w-full animate-pulse bg-muted motion-reduce:animate-none" />
      <img v-else :src="preview!.imageDataUri!" alt="" class="max-h-60 w-full object-cover" />
    </template>
    <template v-else>
      <div
        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-muted"
        aria-hidden="true"
      >
        <img v-if="preview?.faviconDataUri" :src="preview.faviconDataUri" alt="" class="h-4 w-4" />
        <svg
          v-else
          class="h-4 w-4 text-muted-foreground"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M13.828 10.172a4 4 0 010 5.656l-4 4a4 4 0 01-5.656-5.656l1.5-1.5m5.656-5.656l1.5-1.5a4 4 0 115.656 5.656l-4 4a4 4 0 01-5.656 0"
          />
        </svg>
      </div>
      <div class="min-w-0">
        <p
          v-if="loading"
          class="h-3.5 w-32 animate-pulse rounded bg-muted motion-reduce:animate-none"
        />
        <p v-else class="truncate text-xs font-medium text-foreground">{{ preview?.title }}</p>
        <p class="truncate text-[11px] text-muted-foreground">{{ domain }}</p>
      </div>
    </template>
  </a>
</template>

<script setup lang="ts">
interface Props {
  url: string;
}

const props = defineProps<Props>();

interface LinkPreview {
  url: string;
  domain: string;
  title: string | null;
  faviconDataUri: string | null;
  imageDataUri: string | null;
}

const domain = computed(() => {
  try {
    return new URL(props.url).hostname;
  } catch {
    return props.url;
  }
});

const preview = ref<LinkPreview | null>(null);
const loading = ref(true);
const failed = ref(false);

// Set once the fetch resolves -- while `loading` is still true, this stays
// false and the card renders the generic (non-image) loading skeleton,
// since the actual shape isn't known yet.
const isImage = computed(() => !!preview.value?.imageDataUri);

onMounted(async () => {
  try {
    preview.value = await $fetch<LinkPreview>('/api/link-preview', {
      query: { url: props.url },
    });
    // A page that couldn't be reached/parsed (and isn't a direct image
    // link either) comes back with title: null -- nothing worth showing
    // over the plain inline link already in the message text.
    if (!preview.value?.title && !preview.value?.imageDataUri) failed.value = true;
  } catch {
    failed.value = true;
  } finally {
    loading.value = false;
  }
});
</script>
