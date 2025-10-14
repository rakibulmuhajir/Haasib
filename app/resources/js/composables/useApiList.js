import { ref, watch, unref } from 'vue'
import { http } from '@/lib/http'
import { useToasts } from './useToasts'

/**
 * Composable for fetching, paginating, and searching a list of resources.
 * @param {string | Function} url - The API endpoint URL.
 * @param {object} options - Configuration options.
 * @param {ref<string>} options.query - A ref for the search query string.
 * @param {object} options.initialParams - Initial parameters for the API request.
 * @param {number} options.debounce - Debounce time in ms for search queries.
 * @returns {object}
 */
export function useApiList(url, { query = ref(''), initialParams = {}, debounce = 250 } = {}) {
  const items = ref([])
  const loading = ref(false)
  const error = ref('')
  const meta = ref({}) // For pagination data

  const { addToast } = useToasts()

  const fetch = async () => {
    loading.value = true
    error.value = ''
    try {
      const endpoint = typeof url === 'function' ? url() : url
      if (!endpoint) return

      const { data } = await http.get(endpoint, {
        params: {
          ...initialParams,
          q: unref(query),
        },
      })
      items.value = data.data || []
      meta.value = data.meta || {}
    } catch (e) {
      const message = e?.response?.data?.message || 'Failed to load data.'
      error.value = message
      addToast(message, 'danger')
    } finally {
      loading.value = false
    }
  }

  // Watch for changes in the search query and debounce the fetch call
  watch(query, () => { const t = setTimeout(fetch, debounce); return () => clearTimeout(t) })

  return { items, loading, error, meta, fetch }
}
