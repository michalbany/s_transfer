<script setup lang="ts">
import { ref } from 'vue'
import { toast } from 'vue-sonner'
import axios from 'axios'

const fileInput = ref<HTMLInputElement|null>(null)
const loading = ref(false)
const progress = ref(0)
const CHUNK_SIZE = 10 * 1024 * 1024 // 10MB

function handleFileSelect(e: Event) {
  const input = e.target as HTMLInputElement
  if (!input.files || !input.files.length) return
  const file = input.files[0]
  uploadInChunks(file)
}

async function uploadInChunks(file: File) {
  loading.value = true
  progress.value = 0
  const totalChunks = Math.ceil(file.size / CHUNK_SIZE)
  const token = await getUploadToken() // získáme unikátní token od serveru (např. POST /init-upload)

  for (let i = 0; i < totalChunks; i++) {
    const start = i * CHUNK_SIZE
    const end = start + CHUNK_SIZE
    const chunk = file.slice(start, end)

    const formData = new FormData()
    formData.append('file', chunk, `chunk_${i}`)
    formData.append('token', token)
    formData.append('chunk_index', i.toString())
    formData.append('total_chunks', totalChunks.toString())
    formData.append('filename', file.name)

    try {
      await axios.post('/upload-chunk', formData, {
        onUploadProgress: (event) => {
          if (event.total) {
            const chunkProgress = (event.loaded / event.total) * (1/totalChunks) * 100
            progress.value = Math.min(100, progress.value + chunkProgress)
          }
        }
      })
    } catch (error) {
      console.error(error)
      toast.error('Failed to upload chunk.')
      loading.value = false
      return
    }
  }

  // Po nahrání všech chunků server vrátí link v poslední odpovědi, nebo musíme udělat extra request.
  // Předpokládejme, že poslední chunk odpoví s { link: "..."}.
  toast.success('Upload completed!')
  loading.value = false
}

// Funkce pro inicializaci uploadu a získání tokenu
async function getUploadToken(): Promise<string> {
  const response = await axios.post('/init-upload', {})
  return response.data.token
}

</script>

<template>
  <div>
    <input type="file" @change="handleFileSelect" />
    <div v-if="loading">
      Uploading... {{ Math.round(progress) }}%
    </div>
  </div>
</template>
