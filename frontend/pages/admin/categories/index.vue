<template>
  <div class="space-y-6">
    <div class="border border-black p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-display font-bold text-lg">Category Tree</h2>
        <button @click="showAddForm = !showAddForm"
          class="font-label text-[10px] font-semibold uppercase tracking-[0.062em] px-3 py-1.5 border border-black hover:bg-black hover:text-white transition-colors"
        >
          {{ showAddForm ? 'Cancel' : 'Add Category' }}
        </button>
      </div>

      <div v-if="showAddForm" class="border border-zinc-300 p-4 mb-4 space-y-3 bg-zinc-50">
        <input v-model="newCat.name" placeholder="Category name" class="w-full border border-zinc-300 px-3 py-2 text-sm focus:outline-none focus:border-black" />
        <input v-model="newCat.slug" placeholder="Slug (auto-generated if empty)" class="w-full border border-zinc-300 px-3 py-2 text-sm focus:outline-none focus:border-black" />
        <select v-model="newCat.parent_id" class="w-full border border-zinc-300 px-3 py-2 text-sm focus:outline-none focus:border-black"
        >
          <option value="">Top-level parent</option>
          <option v-for="p in parentCategories" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <textarea v-model="newCat.description" placeholder="Description (optional)" rows="2" class="w-full border border-zinc-300 px-3 py-2 text-sm focus:outline-none focus:border-black"></textarea>
        <button @click="createCategory" :disabled="!newCat.name"
          class="font-label text-[10px] font-semibold uppercase tracking-[0.062em] px-4 py-2 bg-black text-white hover:bg-zinc-800 transition-colors disabled:opacity-50"
        >Create</button>
      </div>

      <div v-for="parent in categories" :key="parent.id" class="mb-3">
        <div class="flex items-center justify-between py-2 px-3 bg-zinc-50 border border-black">
          <div class="flex items-center gap-2">
            <span class="font-display font-bold text-sm">{{ parent.name }}</span>
            <span class="text-xs text-zinc-400">/{{ parent.slug }}</span>
          </div>
          <div class="flex gap-2">
            <button @click="editCat = parent" class="text-xs text-zinc-500 hover:text-black">Edit</button>
            <button @click="deleteCategory(parent.id)" class="text-xs text-red-700 hover:text-red-900">Delete</button>
          </div>
        </div>
        <div v-for="child in parent.children" :key="child.id" class="flex items-center justify-between py-2 px-3 pl-8 border border-t-0 border-zinc-200">
          <div class="flex items-center gap-2">
            <span class="text-sm">{{ child.name }}</span>
            <span class="text-xs text-zinc-400">/{{ child.slug }}</span>
          </div>
          <div class="flex gap-2">
            <button @click="editCat = child" class="text-xs text-zinc-500 hover:text-black">Edit</button>
            <button @click="deleteCategory(child.id)" class="text-xs text-red-700 hover:text-red-900">Delete</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="editCat" class="fixed inset-0 bg-black/30 flex items-center justify-center z-50" @click.self="editCat = null"
    >
      <div class="bg-white border border-black p-6 w-full max-w-md space-y-3">
        <h3 class="font-display font-bold">Edit Category</h3>
        <input v-model="editCat.name" class="w-full border border-zinc-300 px-3 py-2 text-sm focus:outline-none focus:border-black" />
        <input v-model="editCat.slug" class="w-full border border-zinc-300 px-3 py-2 text-sm focus:outline-none focus:border-black" />
        <textarea v-model="editCat.description" rows="2" class="w-full border border-zinc-300 px-3 py-2 text-sm focus:outline-none focus:border-black" placeholder="Description"></textarea>
        <div class="flex gap-2 justify-end">
          <button @click="editCat = null" class="text-xs px-4 py-2 border border-zinc-300 hover:border-black transition-colors">Cancel</button>
          <button @click="updateCategory" class="text-xs px-4 py-2 bg-black text-white hover:bg-zinc-800 transition-colors">Save</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin' })

const api = useAdminApi()
const categories = ref<any[]>([])
const showAddForm = ref(false)
const editCat = ref<any>(null)
const newCat = reactive({ name: '', slug: '', parent_id: '', description: '' })

const parentCategories = computed(() => categories.value)

async function loadCategories() {
  try {
    const res = await api.getCategories()
    categories.value = res.data
  } catch {}
}

async function createCategory() {
  try {
    const payload: any = { name: newCat.name }
    if (newCat.slug) payload.slug = newCat.slug
    if (newCat.parent_id) payload.parent_id = Number(newCat.parent_id)
    if (newCat.description) payload.description = newCat.description
    await api.createCategory(payload)
    newCat.name = ''; newCat.slug = ''; newCat.parent_id = ''; newCat.description = ''
    showAddForm.value = false
    await loadCategories()
  } catch {}
}

async function updateCategory() {
  if (!editCat.value) return
  try {
    await api.updateCategory(editCat.value.id, {
      name: editCat.value.name,
      slug: editCat.value.slug,
      description: editCat.value.description,
    })
    editCat.value = null
    await loadCategories()
  } catch {}
}

async function deleteCategory(id: number) {
  if (!confirm('Delete this category?')) return
  try {
    await api.deleteCategory(id)
    await loadCategories()
  } catch {}
}

onMounted(loadCategories)
</script>
