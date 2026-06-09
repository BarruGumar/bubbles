<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useAudio } from '@/Composables/useAudio';

const props = defineProps({
    comments:     { type: Array,  required: true },
    authUser:     { type: Object, default: null },
    commentRoute: { type: String, required: true },
    accentColor:  { type: String, default: '#009ac7' },
});

const commentText = ref('');
const replyTexts  = ref({});
const activeReply = ref(null);
const deletingId  = ref(null);
const pickerOpen      = ref({});
const pickerTimers    = ref({});
const pickerHideTimers = ref({});
const { playSfx } = useAudio();

const REACTIONS = [
    { type: 'like',  emoji: '👍' },
    { type: 'love',  emoji: '❤️' },
    { type: 'laugh', emoji: '😂' },
    { type: 'wow',   emoji: '😮' },
    { type: 'sad',   emoji: '😢' },
];

function reactionEmoji(type) {
    return REACTIONS.find(r => r.type === type)?.emoji ?? '👍';
}

function reactComment(id, type) {
    pickerOpen.value[id] = false;
    router.post(route('comments.like', id), { type }, {
        preserveScroll: true,
        preserveState: true,
    });
}

function startPickerTimer(id) {
    clearTimeout(pickerHideTimers.value[id]);
    pickerTimers.value[id] = setTimeout(() => { pickerOpen.value[id] = true; }, 400);
}

function cancelPickerTimer(id) {
    clearTimeout(pickerTimers.value[id]);
    pickerHideTimers.value[id] = setTimeout(() => { pickerOpen.value[id] = false; }, 300);
}

function keepPickerOpen(id) {
    clearTimeout(pickerHideTimers.value[id]);
}

function toggleReplyInput(id) {
    activeReply.value = activeReply.value === id ? null : id;
}

function submitReply(id, replyRoute) {
    const text = (replyTexts.value[id] ?? '').trim();
    if (!text) return;
    playSfx('send');
    router.post(replyRoute, { content: text }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            replyTexts.value[id] = '';
            activeReply.value = null;
        },
    });
}

function submitComment() {
    const text = commentText.value.trim();
    if (!text) return;
    playSfx('send');
    router.post(props.commentRoute, { content: text }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { commentText.value = ''; },
    });
}

function deleteComment(id) {
    if (deletingId.value) return;
    deletingId.value = id;
    playSfx('off');
    router.delete(route('comments.destroy', id), {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { deletingId.value = null; },
    });
}

function initial(name) { return (name ?? '?')[0].toUpperCase(); }
</script>

<template>
    <div style="margin-top: 12px; border-top: 1px solid rgba(0,154,199,0.06); padding-top: 12px">

        <!-- Top-level comments -->
        <div v-for="c in comments" :key="c.id" style="margin-bottom: 10px">

            <!-- Comment row -->
            <div style="display:flex; gap:8px; align-items:flex-start">
                <!-- Avatar -->
                <span v-if="c.author.avatar" style="position:relative;display:inline-block;border-radius:50%;line-height:0;flex-shrink:0;">
                    <img :src="c.author.avatar" loading="lazy"
                        :style="{ width:'28px', height:'28px', borderRadius:'50%', objectFit:'cover', display:'block',
                                  border:`1.5px solid ${c.author.avatar_color}`, boxShadow:`0 2px 8px ${c.author.avatar_color}44` }" />
                    <span style="position:absolute;inset:0;border-radius:50%;background:linear-gradient(160deg,rgba(255,255,255,.35) 0%,transparent 55%);pointer-events:none;"></span>
                </span>
                <div v-else :style="{ width:'28px', height:'28px', borderRadius:'50%', flexShrink:0, position:'relative',
                    background:`radial-gradient(circle at 38% 30%, rgba(255,255,255,.3), transparent 55%), ${c.author.avatar_color ?? '#009ac7'}`,
                    display:'flex', alignItems:'center', justifyContent:'center', fontSize:'11px',
                    fontWeight:'800', color:'white', boxShadow:`0 2px 8px ${c.author.avatar_color ?? '#009ac7'}44` }">
                    <span style="position:absolute;inset:0;border-radius:50%;background:linear-gradient(160deg,rgba(255,255,255,.25) 0%,transparent 50%);pointer-events:none;"></span>
                    {{ initial(c.author.name) }}
                </div>

                <!-- Bubble + meta -->
                <div style="flex:1; min-width:0">
                    <div class="comment-bubble" style="padding:8px 12px">
                        <Link v-if="c.author.username" :href="route('profile.show', c.author.username)"
                            style="font-size:12px; font-weight:700; color:#3a6478; text-decoration:none">
                            {{ c.author.name }}
                        </Link>
                        <span v-else style="font-size:12px; font-weight:700; color:#3a6478">{{ c.author.name }}</span>
                        <p style="font-size:13px; color:#2a4a5a; margin:2px 0 0; line-height:1.5; white-space:pre-wrap">
                            {{ c.content }}
                        </p>
                    </div>

                    <!-- Meta: time · like picker · reply · delete -->
                    <div style="display:flex; gap:10px; align-items:center; margin-top:3px; padding-left:10px; flex-wrap:wrap">
                        <span style="font-size:11px; color:#b0c0cc">{{ c.created_at }}</span>

                        <!-- Like button with hover picker -->
                        <div style="position:relative; display:inline-flex; align-items:center">
                            <!-- Mini picker -->
                            <div v-if="pickerOpen[c.id]"
                                style="position:absolute; bottom:calc(100% + 4px); left:0; z-index:50;
                                       background:var(--card-bg,#fff); border:1px solid rgba(0,154,199,0.18);
                                       border-radius:20px; padding:4px 8px; display:flex; gap:4px;
                                       box-shadow:0 4px 16px rgba(0,0,0,0.12); white-space:nowrap"
                                @mouseenter="keepPickerOpen(c.id)"
                                @mouseleave="cancelPickerTimer(c.id)">
                                <button v-for="r in REACTIONS" :key="r.type"
                                    @click="reactComment(c.id, r.type)"
                                    :title="r.type"
                                    style="background:none; border:none; cursor:pointer; font-size:16px;
                                           padding:2px 3px; border-radius:8px; transition:transform 0.12s"
                                    @mouseenter="$event.target.style.transform='scale(1.35)'"
                                    @mouseleave="$event.target.style.transform='scale(1)'">
                                    {{ r.emoji }}
                                </button>
                            </div>

                            <button
                                @click="reactComment(c.id, c.user_reaction ?? 'like')"
                                @mouseenter="startPickerTimer(c.id)"
                                @mouseleave="cancelPickerTimer(c.id)"
                                style="background:none; border:none; cursor:pointer; padding:0;
                                       font-size:12px; display:flex; align-items:center; gap:3px;
                                       transition:opacity 0.15s"
                                :style="c.user_reaction ? { color: accentColor, fontWeight: '700' } : { color: '#b0c0cc' }">
                                <span style="font-size:14px; line-height:1">
                                    {{ c.user_reaction ? reactionEmoji(c.user_reaction) : '🤍' }}
                                </span>
                                <span v-if="c.likes_count > 0">{{ c.likes_count }}</span>
                            </button>
                        </div>

                        <!-- Responder -->
                        <button v-if="authUser"
                            @click="toggleReplyInput(c.id)"
                            style="font-size:11px; font-weight:700; color:#b0c0cc; background:none;
                                   border:none; cursor:pointer; padding:0; transition:color 0.15s"
                            :style="activeReply === c.id ? { color: accentColor } : {}"
                            @mouseenter="$event.target.style.color = accentColor"
                            @mouseleave="$event.target.style.color = activeReply === c.id ? accentColor : '#b0c0cc'">
                            Responder
                        </button>

                        <!-- Apagar -->
                        <button v-if="c.is_own" @click="deleteComment(c.id)"
                            :disabled="deletingId === c.id"
                            style="font-size:11px; background:none; border:none;
                                   cursor:pointer; padding:0; transition:color 0.2s, opacity 0.2s"
                            :style="{ color: deletingId === c.id ? '#e05555' : '#c0c8d0', opacity: deletingId === c.id ? 0.5 : 1 }"
                            @mouseenter="deletingId !== c.id && ($event.target.style.color='#e05555')"
                            @mouseleave="deletingId !== c.id && ($event.target.style.color='#c0c8d0')">
                            {{ deletingId === c.id ? '…' : 'Apagar' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Reply input (inline) -->
            <div v-if="activeReply === c.id && authUser"
                style="display:flex; gap:6px; align-items:center; margin-top:6px; padding-left:36px">
                <span v-if="authUser.avatar" style="position:relative;display:inline-block;border-radius:50%;line-height:0;flex-shrink:0;">
                    <img :src="authUser.avatar" loading="lazy"
                        :style="{ width:'22px', height:'22px', borderRadius:'50%', objectFit:'cover', display:'block',
                                  border:`1.5px solid ${authUser.avatar_color ?? '#009ac7'}` }" />
                    <span style="position:absolute;inset:0;border-radius:50%;background:linear-gradient(160deg,rgba(255,255,255,.35) 0%,transparent 55%);pointer-events:none;"></span>
                </span>
                <div v-else :style="{ width:'22px', height:'22px', borderRadius:'50%', flexShrink:0, position:'relative',
                    background:`radial-gradient(circle at 38% 30%, rgba(255,255,255,.3), transparent 55%), ${authUser.avatar_color ?? '#009ac7'}`,
                    display:'flex', alignItems:'center', justifyContent:'center', fontSize:'9px', fontWeight:'800', color:'white' }">
                    <span style="position:absolute;inset:0;border-radius:50%;background:linear-gradient(160deg,rgba(255,255,255,.25) 0%,transparent 50%);pointer-events:none;"></span>
                    {{ initial(authUser.name) }}
                </div>
                <input v-model="replyTexts[c.id]"
                    @keydown.enter.prevent="submitReply(c.id, c.reply_route)"
                    placeholder="Escreve uma resposta..."
                    style="flex:1; min-width:0; background:rgba(240,248,255,0.8);
                           border:1.5px solid rgba(0,154,199,0.15); border-radius:16px;
                           padding:5px 12px; font-size:12px; color:#3a6478; outline:none;
                           font-family:inherit; transition:border-color 0.2s"
                    @focus="$event.target.style.borderColor = accentColor"
                    @blur="$event.target.style.borderColor = 'rgba(0,154,199,0.15)'" />
                <button @click="submitReply(c.id, c.reply_route)"
                    :style="{ padding:'5px 12px', background: accentColor, color:'white',
                              border:'none', borderRadius:'16px', fontSize:'11px',
                              fontWeight:'700', cursor:'pointer', flexShrink:0 }">
                    Enviar
                </button>
            </div>

            <!-- Replies -->
            <div v-if="c.replies && c.replies.length" style="margin-top:6px; padding-left:36px; display:flex; flex-direction:column; gap:6px">
                <div v-for="r in c.replies" :key="r.id" style="display:flex; gap:6px; align-items:flex-start">
                    <!-- Small avatar -->
                    <span v-if="r.author.avatar" style="position:relative;display:inline-block;border-radius:50%;line-height:0;flex-shrink:0;">
                        <img :src="r.author.avatar" loading="lazy"
                            :style="{ width:'22px', height:'22px', borderRadius:'50%', objectFit:'cover', display:'block',
                                      border:`1.5px solid ${r.author.avatar_color}`, boxShadow:`0 2px 6px ${r.author.avatar_color}44` }" />
                        <span style="position:absolute;inset:0;border-radius:50%;background:linear-gradient(160deg,rgba(255,255,255,.35) 0%,transparent 55%);pointer-events:none;"></span>
                    </span>
                    <div v-else :style="{ width:'22px', height:'22px', borderRadius:'50%', flexShrink:0, position:'relative',
                        background:`radial-gradient(circle at 38% 30%, rgba(255,255,255,.3), transparent 55%), ${r.author.avatar_color ?? '#009ac7'}`,
                        display:'flex', alignItems:'center', justifyContent:'center', fontSize:'9px',
                        fontWeight:'800', color:'white' }">
                        <span style="position:absolute;inset:0;border-radius:50%;background:linear-gradient(160deg,rgba(255,255,255,.25) 0%,transparent 50%);pointer-events:none;"></span>
                        {{ initial(r.author.name) }}
                    </div>

                    <!-- Reply bubble + meta -->
                    <div style="flex:1; min-width:0">
                        <div style="background:var(--surface); border:1px solid rgba(78,188,255,.1); border-radius:10px; padding:6px 10px; box-shadow:inset 0 1px 0 rgba(255,255,255,.04);">
                            <Link v-if="r.author.username" :href="route('profile.show', r.author.username)"
                                style="font-size:11px; font-weight:700; color:#3a6478; text-decoration:none">
                                {{ r.author.name }}
                            </Link>
                            <span v-else style="font-size:11px; font-weight:700; color:#3a6478">{{ r.author.name }}</span>
                            <p style="font-size:12px; color:#2a4a5a; margin:2px 0 0; line-height:1.5; white-space:pre-wrap">
                                {{ r.content }}
                            </p>
                        </div>

                        <!-- Reply meta -->
                        <div style="display:flex; gap:8px; align-items:center; margin-top:2px; padding-left:8px; flex-wrap:wrap">
                            <span style="font-size:10px; color:#b0c0cc">{{ r.created_at }}</span>

                            <!-- Like on reply -->
                            <div style="position:relative; display:inline-flex; align-items:center">
                                <div v-if="pickerOpen[r.id]"
                                    style="position:absolute; bottom:calc(100% + 4px); left:0; z-index:50;
                                           background:var(--card-bg,#fff); border:1px solid rgba(0,154,199,0.18);
                                           border-radius:20px; padding:4px 8px; display:flex; gap:4px;
                                           box-shadow:0 4px 16px rgba(0,0,0,0.12); white-space:nowrap"
                                    @mouseenter="keepPickerOpen(r.id)"
                                    @mouseleave="cancelPickerTimer(r.id)">
                                    <button v-for="rx in REACTIONS" :key="rx.type"
                                        @click="reactComment(r.id, rx.type)"
                                        style="background:none; border:none; cursor:pointer; font-size:14px;
                                               padding:2px 3px; border-radius:8px; transition:transform 0.12s"
                                        @mouseenter="$event.target.style.transform='scale(1.35)'"
                                        @mouseleave="$event.target.style.transform='scale(1)'">
                                        {{ rx.emoji }}
                                    </button>
                                </div>

                                <button
                                    @click="reactComment(r.id, r.user_reaction ?? 'like')"
                                    @mouseenter="startPickerTimer(r.id)"
                                    @mouseleave="cancelPickerTimer(r.id)"
                                    style="background:none; border:none; cursor:pointer; padding:0;
                                           font-size:11px; display:flex; align-items:center; gap:2px"
                                    :style="r.user_reaction ? { color: accentColor, fontWeight:'700' } : { color:'#b0c0cc' }">
                                    <span style="font-size:12px; line-height:1">
                                        {{ r.user_reaction ? reactionEmoji(r.user_reaction) : '🤍' }}
                                    </span>
                                    <span v-if="r.likes_count > 0">{{ r.likes_count }}</span>
                                </button>
                            </div>

                            <button v-if="r.is_own" @click="deleteComment(r.id)"
                                :disabled="deletingId === r.id"
                                style="font-size:10px; background:none; border:none;
                                       cursor:pointer; padding:0; transition:color 0.2s, opacity 0.2s"
                                :style="{ color: deletingId === r.id ? '#e05555' : '#c0c8d0', opacity: deletingId === r.id ? 0.5 : 1 }"
                                @mouseenter="deletingId !== r.id && ($event.target.style.color='#e05555')"
                                @mouseleave="deletingId !== r.id && ($event.target.style.color='#c0c8d0')">
                                {{ deletingId === r.id ? '…' : 'Apagar' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Empty state -->
        <p v-if="comments.length === 0"
            style="font-size:12px; color:#b0c0cc; text-align:center; padding:2px 0 10px; font-style:italic">
            Sê o primeiro a comentar!
        </p>

        <!-- New comment input -->
        <div v-if="authUser" style="display:flex; gap:8px; align-items:center; margin-top:4px">
            <span v-if="authUser.avatar" style="position:relative;display:inline-block;border-radius:50%;line-height:0;flex-shrink:0;">
                <img :src="authUser.avatar" loading="lazy"
                    :style="{ width:'28px', height:'28px', borderRadius:'50%', objectFit:'cover', display:'block',
                              border:`1.5px solid ${authUser.avatar_color ?? '#009ac7'}` }" />
                <span style="position:absolute;inset:0;border-radius:50%;background:linear-gradient(160deg,rgba(255,255,255,.35) 0%,transparent 55%);pointer-events:none;"></span>
            </span>
            <div v-else :style="{ width:'28px', height:'28px', borderRadius:'50%', flexShrink:0, position:'relative',
                background:`radial-gradient(circle at 38% 30%, rgba(255,255,255,.3), transparent 55%), ${authUser.avatar_color ?? '#009ac7'}`,
                display:'flex', alignItems:'center', justifyContent:'center', fontSize:'11px', fontWeight:'800', color:'white' }">
                <span style="position:absolute;inset:0;border-radius:50%;background:linear-gradient(160deg,rgba(255,255,255,.25) 0%,transparent 50%);pointer-events:none;"></span>
                {{ initial(authUser.name) }}
            </div>
            <div style="flex:1; display:flex; gap:6px">
                <input v-model="commentText"
                    @keydown.enter.prevent="submitComment"
                    placeholder="Escreve um comentário..."
                    style="flex:1; min-width:0; background:rgba(240,248,255,0.8);
                           border:1.5px solid rgba(0,154,199,0.15); border-radius:20px;
                           padding:7px 14px; font-size:13px; color:#3a6478; outline:none;
                           font-family:inherit; transition:border-color 0.2s"
                    @focus="$event.target.style.borderColor = accentColor"
                    @blur="$event.target.style.borderColor = 'rgba(0,154,199,0.15)'" />
                <button @click="submitComment"
                    :style="{ padding:'7px 14px', background: accentColor, color:'white',
                              border:'none', borderRadius:'20px', fontSize:'12px',
                              fontWeight:'700', cursor:'pointer', whiteSpace:'nowrap',
                              flexShrink:0, transition:'opacity .2s' }"
                    @mouseenter="$event.target.style.opacity='.8'"
                    @mouseleave="$event.target.style.opacity='1'">
                    Enviar
                </button>
            </div>
        </div>

    </div>
</template>

<style scoped>
.comment-bubble {
    background: var(--surface);
    border: 1px solid rgba(78,188,255,.12);
    border-radius: 12px;
    box-shadow: inset 0 1px 0 rgba(255,255,255,.06), 0 2px 8px rgba(0,0,0,.08);
    position: relative;
}
.comment-bubble::before {
    content: '';
    position: absolute;
    left: -6px;
    top: 10px;
    width: 10px; height: 10px;
    background: var(--surface);
    border-left: 1px solid rgba(78,188,255,.12);
    border-bottom: 1px solid rgba(78,188,255,.12);
    transform: rotate(45deg);
}
</style>
