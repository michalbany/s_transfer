<script setup lang="ts">
import { Head } from "@inertiajs/vue3";
import { Link } from "@inertiajs/vue3";
import { ref } from "vue";
import { Button } from "@/Components/ui/button";
import DefaultLayout from "@/Layouts/DefaultLayout.vue";
import { toast } from "vue-sonner";
import axios from 'axios'

// Rozhraní pro položky
interface Item {
    type: "file" | "directory";
    name: string;
    file?: File;
    relativePath: string; // pro zachování struktury
}

const items = ref<Item[]>([]); // Top-level items
const uploadItems = ref<Item[]>([]); // All items for upload
const isDragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const uploaded = ref(false);
const returnLink = ref<string | null>(null);

const loading = ref(false);
const errorMessage = ref<string | null>(null);
const statusMessage = ref("Idle");
const progress = ref(0);

const MAX_SIZE = 5 * 1024 * 1024 * 1024; // 5GB
const CHUNK_SIZE = 500 * 1024 * 1024; // 500MB pro méně chunků

// Funkce pro zpracování přetažení
function onDragOver(e: DragEvent) {
    e.preventDefault();
    isDragging.value = true;
}

function onDragLeave(e: DragEvent) {
    e.preventDefault();
    isDragging.value = false;
}

function onDrop(e: DragEvent) {
    e.preventDefault();
    isDragging.value = false;
    const dt = e.dataTransfer;
    if (!dt) return;
    const itemsList = dt.items;
    processItems(itemsList);
}

// Funkce pro zpracování souborů z inputu
function handleFilesFromInput(e: Event) {
    const input = e.target as HTMLInputElement;
    if (!input.files) return;
    for (const f of input.files) {
        // Přidáme soubor do top-level a upload listu
        const newItem: Item = {
            type: "file",
            name: f.name,
            file: f,
            relativePath: f.name
        };
        items.value.push(newItem);
        uploadItems.value.push(newItem);
    }
}

function fileFromEntry(entry: FileSystemFileEntry): Promise<File> {
    return new Promise((resolve, reject) => {
        entry.file(file => file ? resolve(file) : reject(new Error("No file returned")), reject);
    });
}

// Rekurzivní přečtení celé struktury. Vrací proměnný seznam položek (soubory i složky).
async function readDirectoryRecursive(dirEntry: FileSystemDirectoryEntry, parentPath: string): Promise<Item[]> {
    return new Promise((resolve, reject) => {
        const reader = dirEntry.createReader();
        reader.readEntries(async (entries) => {
            let results: Item[] = [];
            for (const entry of entries) {
                const fullPath = parentPath + entry.name;
                if (entry.isDirectory) {
                    // Přidáme samotnou složku
                    const dirItem: Item = {
                        type: "directory",
                        name: entry.name,
                        relativePath: fullPath
                    };
                    results.push(dirItem);
                    uploadItems.value.push(dirItem); // Přidáme do upload listu

                    // Rekurze pro podsložky a soubory
                    const subDirEntry = entry as FileSystemDirectoryEntry;
                    const subItems = await readDirectoryRecursive(subDirEntry, fullPath + "/");
                    results = results.concat(subItems);
                } else {
                    // Soubor
                    const fileEntry = entry as FileSystemFileEntry;
                    try {
                        const file = await fileFromEntry(fileEntry);
                        const fileItem: Item = {
                            type: "file",
                            name: file.name,
                            file: file,
                            relativePath: fullPath
                        };
                        results.push(fileItem);
                        uploadItems.value.push(fileItem); // Přidáme do upload listu
                    } catch (err: any) {
                        console.error(`Failed to read file ${fullPath}:`, err);
                        toast.error(`Failed to read file ${fullPath}: ${err.message}`);
                    }
                }
            }
            resolve(results);
        }, reject);
    });
}

// Funkce pro zpracování přetažených položek
async function processItems(itemsList: DataTransferItemList) {
    errorMessage.value = null;
    for (let i = 0; i < itemsList.length; i++) {
        const item = itemsList[i];
        const entry = item.webkitGetAsEntry?.();
        if (entry) {
            if (entry.isDirectory) {
                const dirEntry = entry as FileSystemDirectoryEntry;
                // Přidáme pouze samotnou složku do top-level seznamu
                const dirItem: Item = {
                    type: "directory",
                    name: dirEntry.name,
                    relativePath: dirEntry.fullPath.startsWith('/') ? dirEntry.fullPath.substring(1) : dirEntry.fullPath
                };
                items.value.push(dirItem);
                uploadItems.value.push(dirItem);

                // Rekurzivně načteme obsah složky pro upload
                const dirItems = await readDirectoryRecursive(dirEntry, dirItem.relativePath + "/");
                // DirItems jsou přidány do uploadItems.value v readDirectoryRecursive
            } else if (entry.isFile) {
                const fileEntry = entry as FileSystemFileEntry;
                try {
                    const file = await fileFromEntry(fileEntry);
                    const fileItem: Item = {
                        type: "file",
                        name: file.name,
                        file: file,
                        relativePath: file.name // soubor v rootu
                    };
                    items.value.push(fileItem);
                    uploadItems.value.push(fileItem);
                } catch (err: any) {
                    errorMessage.value = "Failed to read a file. " + err.message;
                    toast.error("Failed to read a file. " + err.message);
                    console.error(err);
                }
            }
        } else {
            // Fallback, pokud nemáme webkitGetAsEntry
            const f = item.getAsFile();
            if (f) {
                const fileItem: Item = {
                    type: "file",
                    name: f.name,
                    file: f,
                    relativePath: f.name
                };
                items.value.push(fileItem);
                uploadItems.value.push(fileItem);
            }
        }
    }
}

// Funkce pro spočítání celkové velikosti souborů
async function getTotalSize(): Promise<number> {
    let totalSize = 0;
    for (const item of uploadItems.value) { // Používáme uploadItems pro správné počítání
        if (item.type === "file" && item.file) {
            totalSize += item.file.size;
        }
    }
    return totalSize;
}

let totalChunksOverall = 0;
let chunksUploaded = 0;

// Funkce pro nahrávání souborů a složek
async function uploadFiles() {
    if (!uploadItems.value.length) { // Kontrola uploadItems
        toast.warning("No items selected.");
        return;
    }

    errorMessage.value = null;
    loading.value = true;
    statusMessage.value = "Uploading...";
    progress.value = 0;
    chunksUploaded = 0;
    totalChunksOverall = 0;

    try {
        const totalSize = await getTotalSize();
        if (totalSize > MAX_SIZE) {
            toast.error("The total size exceeds 5GB. Please remove some files.");
            loading.value = false;
            return;
        }

        // 1) Získáme token
        const initRes = await axios.post('/init-upload', {});
        const token = initRes.data.token;

        // 2) Připravíme seznam souborů pro finalize
        const filesInfo: { filename: string; total_chunks: number; relativePath: string; type: "file" | "directory" }[] = [];

        // 3) Spočítáme totalChunksOverall a připravíme filesInfo
        for (const item of uploadItems.value) {
            if (item.type === 'file' && item.file) {
                const totalChunks = Math.ceil(item.file.size / CHUNK_SIZE);
                totalChunksOverall += totalChunks;
                filesInfo.push({
                    filename: item.name,
                    total_chunks: totalChunks,
                    relativePath: item.relativePath,
                    type: "file"
                });
            } else if (item.type === 'directory') {
                // Přidáme prázdnou složku do filesInfo
                filesInfo.push({
                    filename: item.name,
                    total_chunks: 0,
                    relativePath: item.relativePath,
                    type: "directory"
                });
            }
        }

        // 4) Nahrajeme všechny soubory
        for (const fileInfo of filesInfo) {
            if (fileInfo.type === 'file') {
                const fileItem = uploadItems.value.find(item => item.relativePath === fileInfo.relativePath && item.type === 'file');
                if (fileItem && fileItem.file) {
                    await uploadSingleFile(token, fileItem.file, fileInfo.relativePath);
                }
            }
            // Složky přidáme během finalizeUpload
        }

        // 5) Zavoláme finalizeUpload
        statusMessage.value = "Finalizing, this may take a while...";
        const finalizeRes = await axios.post('/finalize-upload', {
            token,
            files: filesInfo
        });
        const link = finalizeRes.data.link;
        toast.success("Upload completed!");
        loading.value = false;
        statusMessage.value = "Done";

        returnLink.value = link;
        uploaded.value = true;

    } catch (err: any) {
        console.error(err);
        toast.error("Error during upload: " + err.message);
        loading.value = false;
        statusMessage.value = "Error";
    }
}

// Pomocná funkce pro nalezení souboru podle relativePath
function findFileByRelativePath(relativePath: string): Item | undefined {
    return uploadItems.value.find(item => item.relativePath === relativePath && item.type === 'file');
}

// Funkce pro nahrávání jednotlivých chunků souboru
async function uploadSingleFile(token: string, file: File, relativePath: string) {
    const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
    for (let i = 0; i < totalChunks; i++) {
        const start = i * CHUNK_SIZE;
        const end = Math.min(start + CHUNK_SIZE, file.size);
        const chunk = file.slice(start, end);
        const formData = new FormData();
        formData.append('file', chunk, `chunk_${i}`);
        formData.append('token', token);
        formData.append('filename', relativePath);
        formData.append('chunk_index', i.toString());
        formData.append('total_chunks', totalChunks.toString());

        await axios.post('/upload-chunk', formData);
        chunksUploaded++;
        progress.value = Math.round((chunksUploaded / totalChunksOverall) * 100);
    }
}

// Funkce pro otevření file inputu
function openFileInput() {
    fileInput.value?.click();
}

// Funkce pro odstranění položky ze seznamu
function removeItem(index: number) {
    const item = items.value[index];

    // Odstraníme z uploadItems
    const uploadIndex = uploadItems.value.findIndex(uploadItem => uploadItem.relativePath === item.relativePath && uploadItem.type === item.type);
    if (uploadIndex !== -1) {
        uploadItems.value.splice(uploadIndex, 1);
    }
    // Odstraníme z items
    items.value.splice(index, 1);

    // console.log("Removing item", item);

    if (item.type === "directory") {
        // odstraníme všechny položky s prefixem
        const prefix = item.relativePath + "/";

        const toRemove = uploadItems.value.filter(uploadItem => uploadItem.relativePath.startsWith(prefix));

        // Odstraníme items
        for (const removeItem of toRemove) {
            const removeIndex = uploadItems.value.findIndex(uploadItem => uploadItem.relativePath === removeItem.relativePath && uploadItem.type === removeItem.type);
            if (removeIndex !== -1) {
                uploadItems.value.splice(removeIndex, 1);
            }
        }
    }
}

const input = ref();
const copied = ref(false);

// Funkce pro kopírování linku
function HandleCopyLinkFromInput() {
    if (!returnLink.value) return;
    if (input.value) {
        input.value.select();
    }
    navigator.clipboard.writeText(returnLink.value);
    toast.success("Link copied to clipboard!");
    copied.value = true;
}
</script>
<template>
    <DefaultLayout>
        <Head title="Upload" />

        <template v-if="uploaded">
            <div class="p-6 py-2 mb-2">
                <div class="flex justify-center items-center flex-col">
                    <svg class="block size-16 text-primary" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24">
                        <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 6L9 17l-5-5"/>
                    </svg>
                    <p class="text-sm mt-2">Package uploaded successfully!</p>
                </div>
            </div>
            
            <h2 class="bg-slate-50 px-4 text-sm text-slate-600 py-1 border-t border-b border-muted">Copy the link</h2>
            
            <div class="mx-4 mt-4 flex items-center gap-2">
                <input ref="input" type="text" class="grow border border-muted rounded-lg p-2" @click="HandleCopyLinkFromInput" :value="returnLink" readonly />
                <Button @click="HandleCopyLinkFromInput" size="icon" variant="secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24">
                        <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
                            <rect width="14" height="14" x="8" y="8" rx="2" ry="2"/>
                            <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>
                        </g>
                    </svg>
                </Button>
            </div>
            
            <span class="text-xs text-slate-600 px-4 mt-2">Files will be available for 7 days</span>
            
            <div class="px-4 mt-4">
                <Button class="w-full" :disabled="!copied">
                    <Link :href="route('packages.create')" class="w-full h-full">
                        Send another
                    </Link>
                </Button>
            </div>
        </template>

        <div v-else>
            <div v-if="!loading" @dragover.prevent="onDragOver" @dragleave.prevent="onDragLeave" @drop="onDrop" class="p-6 py-8 mb-2">
                <div class="flex items-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="bg-primary text-white p-1 rounded-full" width="32" height="32" viewBox="0 0 24 24">
                        <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-7-7v14"/>
                    </svg>
                    <div>
                        <p class="text-lg text-secondary-foreground">Drag & drop files or folders</p>
                        <p class="text-xs text-slate-500">
                            or <span class="text-primary hover:underline cursor-pointer" @click="openFileInput">select manually</span>
                        </p>
                    </div>
                </div>
                <input type="file" multiple @change="handleFilesFromInput" class="hidden" ref="fileInput" />
            </div>

            <!-- Progress indikátor -->
            <div class="p-6 py-2 mb-2" v-else>
                <div class="flex flex-col items-center justify-center">
                    <p class="text-slate-500 text-sm mb-2">{{ statusMessage }}</p>
                    <p class="text-slate-500 text-sm mb-2">{{ progress }}%</p>
                    <div class="w-full bg-gray-200 rounded-full h-2.5 mb-4">
                        <div class="bg-primary h-2.5 rounded-full" :style="{width: progress + '%'}"></div>
                    </div>
                </div>
            </div>

            <!-- Seznam vybraných položek -->
            <h2 class="bg-slate-50 px-4 text-sm text-slate-600 py-1 border-t border-b border-muted">Selected items:</h2>
            <ul class="px-4 my-3" v-if="items.length">
                <li v-for="(item, index) in items" :key="index" class="text-slate-600 text-sm flex items-center justify-between space-y-1">
                    <div>
                        <template v-if="item.type === 'file'">
                            <svg class="size-4 inline-block mr-2 text-primary" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24">
                                <g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
                                    <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>
                                    <path d="M14 2v4a2 2 0 0 0 2 2h4"/>
                                </g>
                            </svg>
                            <span>{{ item.relativePath }}</span>
                        </template>
                        <template v-else>
                            <svg class="size-4 inline-block mr-2 text-primary" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24">
                                <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/>
                            </svg>
                            <span>{{ item.relativePath }}</span>
                        </template>
                    </div>
                    <Button size="icon" variant="ghost" class="h-8 w-8 hover:text-primary" :disabled="loading" @click="removeItem(index)">
                        <svg class="size-4 " xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24">
                            <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18m-2 0v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6m3 0V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2m-6 5v6m4-6v6"/>
                        </svg>
                    </Button>
                </li>
            </ul>
            <span v-else class="px-4 text-slate-500 text-sm block my-2">No items selected.</span>
            <div class="px-4">
                <Button @click="uploadFiles" class="w-full" :disabled="loading">Get a link</Button>
            </div>
        </div>
    </DefaultLayout>
</template>
