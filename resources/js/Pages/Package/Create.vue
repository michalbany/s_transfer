<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import JSZip from 'jszip'

interface Item {
  type: 'file' | 'directory';
  name: string;
  file?: File;           
  entry?: FileSystemEntry;
}

const items = ref<Item[]>([])
const isDragging = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

const loading = ref(false)
const errorMessage = ref<string| null>(null)

function onDragOver(e: DragEvent) {
  e.preventDefault()
  isDragging.value = true
}

function onDragLeave(e: DragEvent) {
  e.preventDefault()
  isDragging.value = false
}

function onDrop(e: DragEvent) {
  e.preventDefault()
  isDragging.value = false
  const dt = e.dataTransfer
  if (!dt) return
  const itemsList = dt.items
  processItems(itemsList)
}

function handleFilesFromInput(e: Event) {
  const input = e.target as HTMLInputElement
  if (!input.files) return
  for (const f of input.files) {
    items.value.push({
      type: 'file',
      name: f.name,
      file: f
    })
  }
}

async function processItems(itemsList: DataTransferItemList) {
  errorMessage.value = null
  for (let i = 0; i < itemsList.length; i++) {
    const item = itemsList[i]
    const entry = item.webkitGetAsEntry?.()
    if (entry) {
      if (entry.isDirectory) {
        items.value.push({
          type: 'directory',
          name: entry.name,
          entry: entry
        })
      } else if (entry.isFile) {
        try {
          const file = await fileFromEntry(entry)
          items.value.push({
            type: 'file',
            name: file.name,
            file: file
          })
        } catch (err: any) {
          errorMessage.value = 'Failed to read a file. ' + err.message
          console.error(err)
        }
      }
    } else {
      const f = item.getAsFile()
      if (f) {
        items.value.push({
          type: 'file',
          name: f.name,
          file: f
        })
      }
    }
  }
}

function fileFromEntry(entry: any): Promise<File> {
  return new Promise((resolve, reject) => {
    entry.file((file: File) => {
      if (file) {
        resolve(file)
      } else {
        reject(new Error('No file returned from entry'))
      }
    }, (err: any) => {
      reject(err)
    })
  })
}

async function readDirectory(directoryEntry: any): Promise<{path:string, file:File}[]> {
  return new Promise((resolve, reject) => {
    const reader = directoryEntry.createReader()
    reader.readEntries(async (entries: any[]) => {
      let results: {path:string, file:File}[] = []
      for (const e of entries) {
        if (e.isFile) {
          try {
            const file = await fileFromEntry(e)
            results.push({path: e.fullPath, file: file})
          } catch (err: any) {
            errorMessage.value = 'Failed to read a file in directory: ' + err.message
            console.error(err)
            // Pokračujeme, nebo reject? Zatím jen pokračujeme.
          }
        } else if (e.isDirectory) {
          try {
            const subEntries = await readDirectory(e)
            results = results.concat(subEntries)
          } catch (err: any) {
            errorMessage.value = 'Failed to read a subdirectory: ' + err.message
            console.error(err)
          }
        }
      }
      resolve(results)
    }, (err: any) => {
      reject(err)
    })
  })
}

async function uploadZip() {
  if (!items.value.length) {
    alert('No items selected.')
    return
  }

  errorMessage.value = null
  loading.value = true

  const zip = new JSZip()

  try {
    for (const item of items.value) {
      if (item.type === 'file' && item.file) {
        zip.file(item.file.name, item.file)
      } else if (item.type === 'directory' && item.entry) {
        const directoryFiles = await readDirectory(item.entry)
        for (const df of directoryFiles) {
          const relativePath = df.path.startsWith('/') ? df.path.slice(1) : df.path
          zip.file(relativePath, df.file)
        }
      }
    }

    const content = await zip.generateAsync({type:"blob"})

    const formData = new FormData()
    formData.append('file', content, 'package.zip')

    router.post(route('packages.store'), formData, {
      onError: (errors) => {
        loading.value = false
        errorMessage.value = 'Failed to upload. Please try again.'
        console.error(errors)
      },
      onSuccess: () => {
        loading.value = false
        // Inertia will handle redirect to success page from server response
      },
      onFinish: () => {
        // Nothing special here
      }
    })
  } catch (err: any) {
    loading.value = false
    errorMessage.value = 'Error during zipping files: ' + err.message
    console.error(err)
  }
}

function openFileInput() {
  fileInput.value?.click()
}

function removeItem(index: number) {
  items.value.splice(index, 1)
}
</script>

<template>
<Head title="Upload" />
<div>
  <h1>S Transfer</h1>
  
  <div 
    @dragover.prevent="onDragOver"
    @dragleave.prevent="onDragLeave"
    @drop="onDrop"
  >
    <p>Drag & drop files or folders here</p>
    <p>or <span @click="openFileInput">select manually</span></p>
    <input type="file" multiple @change="handleFilesFromInput" class="hidden" ref="fileInput" />
  </div>

  <h2>Selected items:</h2>
  <ul>
    <li v-for="(item, index) in items" :key="index">
      <template v-if="item.type === 'file'">
        File: {{ item.name }}
      </template>
      <template v-else>
        Folder: {{ item.name }}
      </template>
      <button @click="removeItem(index)">Remove</button>
    </li>
  </ul>

  <div v-if="loading">Uploading, please wait...</div>
  <div v-if="errorMessage" style="color:red;">{{ errorMessage }}</div>

  <button @click="uploadZip" :disabled="loading">Upload</button>
</div>
</template>
