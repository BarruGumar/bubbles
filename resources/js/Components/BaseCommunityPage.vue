<script setup>
defineProps({
  community: { type: Object, required: true },
  postsCount: { type: Number, default: 0 },
})
</script>

<template>
  <div style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 22px; border: 1px solid #4ebcff22; box-shadow: 0 8px 32px #009ac70e; padding: 36px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 28px;">
    <div :style="{ width: '80px', height: '80px', borderRadius: '50%', flexShrink: 0, background: community.cover_color || community.color, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '28px', fontWeight: '900', color: 'white', boxShadow: `0 4px 24px ${community.color}55`, letterSpacing: '-.02em' }">{{ community.label.replace('#', '').charAt(0).toUpperCase() }}</div>

    <div style="flex: 1; min-width: 0;">
      <h1 style="font-size: 22px; font-weight: 900; color: #1a3a4a; margin: 0 0 4px; letter-spacing: -.02em;">{{ community.title }}</h1>
      <p style="font-size: 12px; color: #5a7a8a; margin: 0 0 8px;">{{ community.tagline }}</p>
      <p style="font-size: 13px; font-weight: 600; margin: 0 0 12px;" :style="{ color: community.color }">{{ community.members }} membros</p>
      <p style="font-size: 12px; color: #6a8798; margin: 0 0 16px; line-height: 1.5;">{{ community.description }}</p>

      <div style="margin-bottom: 18px;">
        <p style="font-size: 10px; color: #8ba0b0; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; margin: 0 0 6px;">Atividade</p>
        <p style="font-size: 9px; color: #b0c0cc; margin: 3px 0 0;">{{ postsCount }} posts</p>
      </div>

      <button :style="{ padding: '9px 24px', borderRadius: '99px', border: 'none', background: community.color, color: 'white', fontSize: '12px', fontWeight: '700' }">Entrar na comunidade</button>
    </div>
  </div>

  <div style="background: rgba(255,255,255,0.88); backdrop-filter: blur(20px); border-radius: 18px; border: 1px solid #4ebcff22; box-shadow: 0 4px 16px #009ac70a; padding: 18px; margin-bottom: 16px;">
    <p style="font-size: 11px; font-weight: 800; color: #8ba0b0; text-transform: uppercase; letter-spacing: .08em; margin: 0 0 8px;">Modelo base da comunidade</p>
    <ul style="margin: 0; padding-left: 18px; color: #2a4a5a; font-size: 13px; line-height: 1.6;">
      <li v-for="(rule, index) in community.guidelines" :key="index">{{ rule }}</li>
    </ul>
  </div>
</template>
