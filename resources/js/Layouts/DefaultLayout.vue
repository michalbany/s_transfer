<script setup lang="ts">
import { Toaster } from "@/Components/ui/sonner";
import ApplicationLogo from "@/Components/ApplicationLogo.vue";
import { Link } from "@inertiajs/vue3";
import { Button } from "@/Components/ui/button";
</script>
<template>
    <Toaster />

    <div
        class="min-h-screen relative bg-white selection:bg-primary selection:text-white flex flex-col"
    >
        <div
            class="absolute h-full w-full bg-[radial-gradient(#e5e7eb_1px,transparent_1px)] [background-size:16px_16px] [mask-image:radial-gradient(ellipse_50%_50%_at_50%_50%,#000_70%,transparent_100%)]"
        ></div>
        <div
            class="absolute z-50 right-0 left-0 h-20 py-3 mx-6 flex items-center justify-between border-b border-muted"
        >
            <ApplicationLogo class="h-full" />
            <Button class="btn btn-primary">
                <Link :href="route('packages.create')" class="w-full h-full">
                    Send Package
                </Link>
            </Button>
        </div>

        <div
            class="container flex grow items-center sm:justify-start justify-center relative"
        >
            <div class="bg-white py-4 mb-16 rounded-lg shadow-md w-[300px]">
                <ApplicationLogo class="px-4 h-20 w-full" />
                <hr class="border-muted mb-5" />
                <slot />
            </div>
        </div>

        <div
            class="absolute bottom-0 right-0 left-0 py-3 mx-6 flex items-center justify-between"
        >
            <div class="text-slate-600 text-xs">
                <span>&copy; 2024 1000S</span>
            </div>
            <div class="text-slate-600 text-xs">
                <span class="mr-2">Beta Release</span>
                <a
                    href="https://www.instagram.com/michael_bany"
                    target="_blank"
                    class="text-primary hover:underline"
                    >Contact Support</a
                >
            </div>
        </div>
    </div>
</template>
