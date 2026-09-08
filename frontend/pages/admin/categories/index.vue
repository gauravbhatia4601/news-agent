<template>
  <div class="space-y-6">
    <!-- Header + Add -->
    <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-admin-sidebar/5 flex items-center justify-center"><Tag class="w-5 h-5 text-admin-accent" /></div>
          <div>
            <h2 class="text-[15px] font-semibold text-admin-text">Category Tree</h2>
            <p class="text-xs text-admin-muted">Manage news categories and subcategories</p>
          </div>
        </div>
        <button @click="showAddForm = !showAddForm" class="px-4 py-2.5 bg-admin-sidebar hover:bg-slate-800 text-white rounded-[14px] text-sm font-medium transition-all flex items-center gap-2"
        >
          <Plus v-if="!showAddForm" class="w-4 h-4" />
          <X v-else class="w-4 h-4" />
          {{ showAddForm ? 'Cancel' : 'Add Category' }}
        </button>
      </div>

      <!-- Add Form -->
      <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0">
        <div v-if="showAddForm" class="mt-5 bg-slate-50 rounded-[14px] border border-admin-border p-5 space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-medium text-admin-muted uppercase tracking-wider">Category Name</label>
              <input v-model="newCat.name" placeholder="e.g. Politics" class="w-full bg-admin-surface border border-admin-border rounded-[14px] px-4 py-2.5 text-sm text-admin-text focus-ring transition-colors" />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-medium text-admin-muted uppercase tracking-wider">Slug</label>
              <input v-model="newCat.slug" placeholder="Auto-generated if empty" class="w-full bg-admin-surface border border-admin-border rounded-[14px] px-4 py-2.5 text-sm text-admin-text focus-ring transition-colors" />
            </div>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="text-xs font-medium text-admin-muted uppercase tracking-wider">Parent</label>
              <select v-model="newCat.parent_id" class="w-full bg-admin-surface border border-admin-border rounded-[14px] px-4 py-2.5 text-sm text-admin-text focus-ring transition-colors"
              >
                <option value="">Top-level parent</option>
                <option v-for="p in parentCategories" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-admin-muted uppercase tracking-wider">Description</label>
            <textarea v-model="newCat.description" placeholder="Optional description" rows="2" class="w-full bg-admin-surface border border-admin-border rounded-[14px] px-4 py-2.5 text-sm text-admin-text focus-ring transition-colors"></textarea>
          </div>
          <div class="flex justify-end">
            <button @click="createCategory" :disabled="!newCat.name" class="px-6 py-2.5 bg-admin-sidebar hover:bg-slate-800 text-white rounded-[14px] text-sm font-medium transition-all disabled:opacity-50 flex items-center gap-2"
            >
              <Check v-if="!creating" class="w-4 h-4" />
              <Loader2 v-else class="w-4 h-4 animate-spin" />
              Create Category
            </button>
          </div>
        </div>
      </Transition>
    </div>

    <!-- Tree -->
    <div class="bg-admin-surface rounded-[14px] border border-admin-border/80 shadow-[0_1px_2px_rgba(0,0,0,0.04)] p-6">
      <div v-if="!categories?.length" class="text-center py-12">
        <Tag class="w-8 h-8 mx-auto mb-3 text-slate-400" />
        <p class="text-sm text-admin-muted">No categories yet</p>
      </div>

      <div class="space-y-2">
        <div v-for="parent in categories" :key="parent.id" class="">
          <!-- Parent row -->
          <div class="flex items-center justify-between py-3 px-4 rounded-xl bg-admin-sidebar text-white">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-lg bg-admin-surface/15 flex items-center justify-center text-xs font-bold">
                {{ parent.name.charAt(0).toUpperCase() }}
              </div>
              <div>
                <span class="text-sm font-bold">{{ parent.name }}</span>
                <span class="text-xs text-white/50 ml-2">/{{ parent.slug }}</span>
              </div>
            </div>
            <div class="flex items-center gap-1">
              <button @click="editCat = parent" class="p-1.5 text-white/60 hover:text-white hover:bg-admin-surface/10 rounded-lg transition-colors" title="Edit"><Edit class="w-3.5 h-3.5" /></button>
              <button @click="deleteCategory(parent.id)" class="p-1.5 text-white/60 hover:text-red-400 hover:bg-admin-surface/10 rounded-lg transition-colors" title="Delete"><Trash2 class="w-3.5 h-3.5" /></button>
            </div>
          </div>

          <!-- Children container with connector line -->
          <div v-if="parent.children?.length" class="relative ml-6 mt-1 space-y-1">
            <!-- Vertical connector line -->
            <div class="absolute left-3 top-0 bottom-0 w-px bg-slate-200"></div>

            <div v-for="(child, idx) in parent.children" :key="child.id" class="relative flex items-center">
              <!-- Horizontal connector -->
              <div class="absolute -left-3 top-1/2 w-6 h-px bg-slate-200" />
              <!-- Branch dot -->
              <div class="absolute -left-1 top-1/2 -translate-y-1/2 w-2 h-2 rounded-full bg-slate-400 ring-4 ring-white" />

              <div class="flex-1 ml-5 flex items-center justify-between py-2.5 px-4 rounded-xl bg-slate-50 hover:bg-slate-100 border border-admin-border transition-colors">
                <div class="flex items-center gap-3">
                  <span class="text-sm font-medium text-admin-text">{{ child.name }}</span>
                  <span class="text-xs text-admin-muted">/{{ child.slug }}</span>
                </div>
                <div class="flex items-center gap-1">
                  <button @click="editCat = child" class="p-1.5 text-admin-muted hover:text-admin-text hover:bg-slate-200 rounded-lg transition-colors" title="Edit"><Edit class="w-3.5 h-3.5" /></button>
                  <button @click="deleteCategory(child.id)" class="p-1.5 text-admin-muted hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete"><Trash2 class="w-3.5 h-3.5" /></button>
                </div>
              </div>
            </div>
          </div>

          <!-- Empty state for no children -->
          <div v-else-if="!parent.children?.length" class="ml-6 mt-1">
            <div class="flex items-center">
              <div class="absolute -left-3 top-1/2 w-6 h-px bg-slate-200" />
              <div class="absolute -left-1 top-1/2 -translate-y-1/2 w-2 h-2 rounded-full bg-slate-400 ring-4 ring-white" />
              <p class="ml-5 text-xs text-admin-muted py-2">No subcategories</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <div v-if="editCat" class="fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center z-50" @click.self="editCat = null"
    >
      <div class="bg-admin-surface rounded-[14px] shadow-2xl w-full max-w-md p-6 border border-admin-border">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-10 h-10 rounded-xl bg-admin-sidebar/5 flex items-center justify-center"><Edit class="w-5 h-5 text-admin-accent" /></div>
          <h2 class="text-[18px] font-semibold text-admin-text">Edit Category</h2>
        </div>

        <div class="space-y-4">
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-admin-muted uppercase tracking-wider">Name</label>
            <input v-model="editCat.name" class="w-full bg-slate-50 border border-admin-border rounded-[14px] px-4 py-2.5 text-sm text-admin-text focus-ring transition-colors" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-admin-muted uppercase tracking-wider">Slug</label>
            <input v-model="editCat.slug" class="w-full bg-slate-50 border border-admin-border rounded-[14px] px-4 py-2.5 text-sm text-admin-text focus-ring transition-colors" />
          </div>
          <div class="space-y-1.5">
            <label class="text-xs font-medium text-admin-muted uppercase tracking-wider">Description</label>
            <textarea v-model="editCat.description" rows="2" class="w-full bg-slate-50 border border-admin-border rounded-[14px] px-4 py-2.5 text-sm text-admin-text focus-ring transition-colors" placeholder="Description"></textarea>
          </div>
          <div class="flex gap-2 justify-end pt-2">
            <button @click="editCat = null" class="px-4 py-2.5 text-sm border border-admin-border rounded-[14px] hover:bg-slate-50 text-slate-600 transition-colors">Cancel</button>
            <button @click="updateCategory" class="px-4 py-2.5 text-sm bg-admin-sidebar text-white rounded-[14px] font-medium hover:bg-slate-800 transition-colors">Save Changes</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Tag, Plus, X, Check, Loader2, Edit, Trash2 } from 'lucide-vue-next'

definePageMeta({ layout: 'admin', middleware: 'admin' })

const api = useAdminApi()
const categories = ref<any[]>([])
const showAddForm = ref(false)
const creating = ref(false)
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
  creating.value = true
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
  creating.value = false
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
