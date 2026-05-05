<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue'
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue'
import DeleteUserForm from './Partials/DeleteUserForm.vue'
import { Head } from '@inertiajs/vue3'

defineProps({
    mustVerifyEmail: Boolean,
    status: String,
})
</script>

<template>
    <Head title="Definições" />

    <AuthenticatedLayout>
        <div style="max-width: 680px; margin: 0 auto; padding: 40px 20px 80px; display: flex; flex-direction: column; gap: 16px;">

            <h1 style="font-size: 20px; font-weight: 900; color: #1a3a4a; margin: 0 0 8px; letter-spacing: -.5px;">Definições</h1>

            <!-- Profile info card -->
            <div style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 20px; border: 1px solid #4ebcff22; box-shadow: 0 4px 20px #009ac70c; padding: 28px 32px;">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                />
            </div>

            <!-- Password card -->
            <div style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 20px; border: 1px solid #4ebcff22; box-shadow: 0 4px 20px #009ac70c; padding: 28px 32px;">
                <UpdatePasswordForm />
            </div>

            <!-- Danger zone card -->
            <div style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 20px; border: 1px solid #e05555 22; box-shadow: 0 4px 20px #e0555508; padding: 28px 32px; border-color: #e0555522;">
                <DeleteUserForm />
            </div>

        </div>
    </AuthenticatedLayout>
</template>
