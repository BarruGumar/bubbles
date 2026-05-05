<script setup>
import { computed } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'

defineProps({
    mustVerifyEmail: Boolean,
    status: String,
})

const user = usePage().props.auth.user

const COLORS = ['#009ac7', '#4ebcff', '#2ea87e', '#e07b4a', '#9b6bdf', '#c74a6b', '#e0a040', '#6b9bdf']

const form = useForm({
    name:         user.name,
    email:        user.email,
    username:     user.username     ?? '',
    bio:          user.bio          ?? '',
    avatar_color: user.avatar_color ?? '#009ac7',
})

const initial = computed(() => (form.name || '?')[0].toUpperCase())

function fieldStyle(hasFocus) {
    return 'background:#f0f8ff;border:1.5px solid #4ebcff44;border-radius:11px;padding:10px 14px;font-size:13px;color:#1a3a4a;outline:none;font-family:inherit;width:100%;box-sizing:border-box;transition:border-color .2s;'
}
</script>

<template>
    <section>
        <header style="margin-bottom: 24px;">
            <h2 style="font-size: 15px; font-weight: 800; color: #1a3a4a; margin: 0 0 4px;">Informação de perfil</h2>
            <p style="font-size: 12px; color: #8ba0b0; margin: 0;">Nome, username, bio e avatar.</p>
        </header>

        <!-- Avatar preview + color picker -->
        <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 24px;">
            <div :style="{
                width: '64px', height: '64px', borderRadius: '50%',
                background: form.avatar_color,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                fontSize: '26px', fontWeight: '900', color: 'white',
                boxShadow: `0 4px 18px ${form.avatar_color}44`,
                flexShrink: 0,
                transition: 'background .3s, box-shadow .3s',
            }">{{ initial }}</div>
            <div>
                <p style="font-size: 11px; font-weight: 700; color: #5a7a8a; text-transform: uppercase; letter-spacing: .06em; margin: 0 0 8px;">Cor do avatar</p>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button
                        v-for="c in COLORS"
                        :key="c"
                        type="button"
                        @click="form.avatar_color = c"
                        :style="{
                            width: '24px', height: '24px', borderRadius: '50%', background: c, border: 'none', cursor: 'pointer',
                            boxShadow: form.avatar_color === c ? `0 0 0 3px white, 0 0 0 5px ${c}` : 'none',
                            transition: 'box-shadow .2s',
                        }"
                    />
                </div>
            </div>
        </div>

        <form @submit.prevent="form.patch(route('profile.update'))" style="display: flex; flex-direction: column; gap: 18px;">

            <!-- Name -->
            <div style="display: flex; flex-direction: column; gap: 6px;">
                <label style="font-size: 11px; font-weight: 700; color: #5a7a8a; text-transform: uppercase; letter-spacing: .06em;">Nome</label>
                <input
                    v-model="form.name"
                    type="text"
                    required
                    autocomplete="name"
                    :style="fieldStyle()"
                    @focus="$event.target.style.borderColor='#009ac7'"
                    @blur="$event.target.style.borderColor='#4ebcff44'"
                />
                <p v-if="form.errors.name" style="font-size: 11px; color: #e05555; margin: 0;">{{ form.errors.name }}</p>
            </div>

            <!-- Username -->
            <div style="display: flex; flex-direction: column; gap: 6px;">
                <label style="font-size: 11px; font-weight: 700; color: #5a7a8a; text-transform: uppercase; letter-spacing: .06em;">Username</label>
                <div style="position: relative;">
                    <span style="position: absolute; left: 13px; top: 50%; transform: translateY(-50%); font-size: 13px; color: #009ac7; font-weight: 700;">@</span>
                    <input
                        v-model="form.username"
                        type="text"
                        autocomplete="off"
                        placeholder="o_teu_username"
                        style="background:#f0f8ff;border:1.5px solid #4ebcff44;border-radius:11px;padding:10px 14px 10px 28px;font-size:13px;color:#1a3a4a;outline:none;font-family:inherit;width:100%;box-sizing:border-box;transition:border-color .2s;"
                        @focus="$event.target.style.borderColor='#009ac7'"
                        @blur="$event.target.style.borderColor='#4ebcff44'"
                    />
                </div>
                <p style="font-size: 10px; color: #b0c0cc; margin: 0;">Só letras minúsculas, números e underscores. Aparece no link do teu perfil.</p>
                <p v-if="form.errors.username" style="font-size: 11px; color: #e05555; margin: 0;">{{ form.errors.username }}</p>
            </div>

            <!-- Email -->
            <div style="display: flex; flex-direction: column; gap: 6px;">
                <label style="font-size: 11px; font-weight: 700; color: #5a7a8a; text-transform: uppercase; letter-spacing: .06em;">Email</label>
                <input
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="username"
                    :style="fieldStyle()"
                    @focus="$event.target.style.borderColor='#009ac7'"
                    @blur="$event.target.style.borderColor='#4ebcff44'"
                />
                <p v-if="form.errors.email" style="font-size: 11px; color: #e05555; margin: 0;">{{ form.errors.email }}</p>
            </div>

            <!-- Bio -->
            <div style="display: flex; flex-direction: column; gap: 6px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <label style="font-size: 11px; font-weight: 700; color: #5a7a8a; text-transform: uppercase; letter-spacing: .06em;">Bio</label>
                    <span style="font-size: 10px; color: #b0c0cc;">{{ form.bio.length }}/300</span>
                </div>
                <textarea
                    v-model="form.bio"
                    maxlength="300"
                    rows="3"
                    placeholder="Conta um pouco sobre ti..."
                    style="background:#f0f8ff;border:1.5px solid #4ebcff44;border-radius:11px;padding:10px 14px;font-size:13px;color:#1a3a4a;outline:none;font-family:inherit;width:100%;box-sizing:border-box;resize:vertical;transition:border-color .2s;"
                    @focus="$event.target.style.borderColor='#009ac7'"
                    @blur="$event.target.style.borderColor='#4ebcff44'"
                />
                <p v-if="form.errors.bio" style="font-size: 11px; color: #e05555; margin: 0;">{{ form.errors.bio }}</p>
            </div>

            <!-- Verification warning -->
            <div v-if="mustVerifyEmail && user.email_verified_at === null" style="background: #fff9ec; border: 1px solid #e0a04044; border-radius: 10px; padding: 12px 14px; font-size: 12px; color: #8a6020;">
                O teu email não está verificado.
                <Link :href="route('verification.send')" method="post" as="button" style="color: #009ac7; font-weight: 700; background: none; border: none; cursor: pointer; font-size: 12px;">
                    Reenviar verificação.
                </Link>
                <div v-if="status === 'verification-link-sent'" style="margin-top: 6px; color: #2ea87e; font-weight: 600;">
                    Link enviado!
                </div>
            </div>

            <!-- Save button -->
            <div style="display: flex; align-items: center; gap: 14px;">
                <button
                    type="submit"
                    :disabled="form.processing"
                    style="padding: 11px 28px; border-radius: 12px; background: #009ac7; border: none; color: white; font-size: 13px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 14px #009ac730; transition: opacity .2s;"
                    :style="{ opacity: form.processing ? 0.7 : 1 }"
                >Guardar</button>
                <Transition name="fade">
                    <span v-if="form.recentlySuccessful" style="font-size: 12px; color: #2ea87e; font-weight: 600;">✓ Guardado</span>
                </Transition>
            </div>
        </form>
    </section>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .3s }
.fade-enter-from, .fade-leave-to       { opacity: 0 }
</style>
