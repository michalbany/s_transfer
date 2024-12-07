<script setup lang="ts">
import { Head, router } from "@inertiajs/vue3";
import { ref } from "vue";
import JSZip from "jszip";
import { Button } from "@/Components/ui/button";
import DefaultLayout from "@/Layouts/DefaultLayout.vue";
import { toast } from "vue-sonner";

interface Item {
    type: "file" | "directory";
    name: string;
    file?: File;
    entry?: FileSystemEntry;
}

const items = ref<Item[]>([]);
const isDragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const loading = ref(false);
const errorMessage = ref<string | null>(null);

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

function handleFilesFromInput(e: Event) {
    const input = e.target as HTMLInputElement;
    if (!input.files) return;
    for (const f of input.files) {
        items.value.push({
            type: "file",
            name: f.name,
            file: f,
        });
    }
}

async function processItems(itemsList: DataTransferItemList) {
    errorMessage.value = null;
    for (let i = 0; i < itemsList.length; i++) {
        const item = itemsList[i];
        const entry = item.webkitGetAsEntry?.();
        if (entry) {
            if (entry.isDirectory) {
                items.value.push({
                    type: "directory",
                    name: entry.name,
                    entry: entry,
                });
            } else if (entry.isFile) {
                try {
                    const file = await fileFromEntry(entry);
                    items.value.push({
                        type: "file",
                        name: file.name,
                        file: file,
                    });
                } catch (err: any) {
                    errorMessage.value =
                        "Failed to read a file. " + err.message;
                        toast.error("Failed to read a file. " + err.message);
                    console.error(err);
                }
            }
        } else {
            const f = item.getAsFile();
            if (f) {
                items.value.push({
                    type: "file",
                    name: f.name,
                    file: f,
                });
            }
        }
    }
}

function fileFromEntry(entry: any): Promise<File> {
    return new Promise((resolve, reject) => {
        entry.file(
            (file: File) => {
                if (file) {
                    resolve(file);
                } else {
                    reject(new Error("No file returned from entry"));
                }
            },
            (err: any) => {
                reject(err);
            }
        );
    });
}

async function readDirectory(
    directoryEntry: any
): Promise<{ path: string; file: File }[]> {
    return new Promise((resolve, reject) => {
        const reader = directoryEntry.createReader();
        reader.readEntries(
            async (entries: any[]) => {
                let results: { path: string; file: File }[] = [];
                for (const e of entries) {
                    if (e.isFile) {
                        try {
                            const file = await fileFromEntry(e);
                            results.push({ path: e.fullPath, file: file });
                        } catch (err: any) {
                            errorMessage.value =
                                "Failed to read a file in directory: " +
                                err.message;
                                toast.error("Failed to read a file in directory: " + err.message);
                            console.error(err);
                            // Pokračujeme, nebo reject? Zatím jen pokračujeme.
                        }
                    } else if (e.isDirectory) {
                        try {
                            const subEntries = await readDirectory(e);
                            results = results.concat(subEntries);
                        } catch (err: any) {
                            errorMessage.value =
                                "Failed to read a subdirectory: " + err.message;

                            toast.error("Failed to read a subdirectory: " + err.message);
                            console.error(err);
                        }
                    }
                }
                resolve(results);
            },
            (err: any) => {
                reject(err);
            }
        );
    });
}

async function uploadZip() {
    if (!items.value.length) {
        toast.warning("No items selected.");
        return;
    }

    errorMessage.value = null;
    loading.value = true;

    const zip = new JSZip();

    try {
        for (const item of items.value) {
            if (item.type === "file" && item.file) {
                zip.file(item.file.name, item.file);
            } else if (item.type === "directory" && item.entry) {
                const directoryFiles = await readDirectory(item.entry);
                for (const df of directoryFiles) {
                    const relativePath = df.path.startsWith("/")
                        ? df.path.slice(1)
                        : df.path;
                    zip.file(relativePath, df.file);
                }
            }
        }

        const content = await zip.generateAsync({ type: "blob" });

        const formData = new FormData();
        formData.append("file", content, "package.zip");

        router.post(route("packages.store"), formData, {
            onError: (errors) => {
                loading.value = false;
                errorMessage.value = "Failed to upload. Please try again.";
                toast.error("Failed to upload. Please try again.");
                console.error(errors);
            },
            onSuccess: () => {
                loading.value = false;
                // Inertia will handle redirect to success page from server response
            },
            onFinish: () => {
                // Nothing special here
            },
        });
    } catch (err: any) {
        loading.value = false;

        errorMessage.value = "Error during zipping files: " + err.message;
        toast.error("Error during zipping files: " + err.message);
        console.error(err);
    }
}

function openFileInput() {
    fileInput.value?.click();
}

function removeItem(index: number) {
    items.value.splice(index, 1);
}
</script>

<template>
    <DefaultLayout>
        <Head title="Upload" />
        <div>
            <div
                @dragover.prevent="onDragOver"
                @dragleave.prevent="onDragLeave"
                @drop="onDrop"
                class="p-6 py-8 mb-2"
                v-if="!loading"
            >
            <div class="flex items-center gap-3">
                <!-- Icon + -->
                <svg xmlns="http://www.w3.org/2000/svg" class="bg-primary text-white p-1 rounded-full" width="32" height="32" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14m-7-7v14"/></svg>
                <div>
                    <p class="text-lg text-secondary-foreground">Drag & drop files</p>
                    <p class="text-xs text-slate-500">or <span class="text-primary hover:underline cursor-pointer" @click="openFileInput">select manually</span></p>
                </div>
            </div>
                <input
                    type="file"
                    multiple
                    @change="handleFilesFromInput"
                    class="hidden"
                    ref="fileInput"
                />
            </div>
            <div
                class="p-6 py-2 mb-2"
                v-else
            >
            <div class="flex justify-center items-center flex-col">
                <svg class="block animate-spin size-16 text-slate-200" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
                <p class="text-slate-500 text-sm mt-2">Uploading...</p>
            </div>
            </div>

            <h2 class="bg-slate-50 px-4 text-sm text-slate-600 py-1 border-t border-b border-muted">Selected items:</h2>
            <ul class="px-4 my-3" v-if="items.length">
                <li v-for="(item, index) in items" :key="index" class="text-slate-600 text-sm flex items-center justify-between space-y-1">
                    <div>
                        <template v-if="item.type === 'file'">
                            <svg class="size-4 inline-block mr-2 text-primary" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/></g></svg>
                            <span>{{ item.name }}</span>
                        </template>
                        <template v-else>
                            <svg class="size-4 inline-block mr-2 text-primary" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>
                            <span>{{ item.name }}</span>
                        </template>
                    </div>
                    <button @click="removeItem(index)"><svg class="size-4 inline-block mr-2 text-slate-300 hover:text-red-500 transition-colors" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6h18m-2 0v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6m3 0V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2m-6 5v6m4-6v6"/></svg></button>
                </li>
            </ul>
            <span v-else class="px-4 text-slate-500 text-sm block my-2">No items selected.</span>
            <!-- <div v-if="errorMessage" style="color: red">{{ errorMessage }}</div> -->

            <div class="px-4">
                <Button @click="uploadZip" class="w-full" :disabled="loading">Get a link</Button>
            </div>
        </div>
    </DefaultLayout>
</template>
