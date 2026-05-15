<template>
    <div
        style="
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            border: 1px solid #4ebcff1a;
            box-shadow: 0 2px 12px #009ac708;
            padding: 20px;
        "
    >
        <div style="display: flex; gap: 14px; align-items: flex-start">
            <!-- Avatar -->
            <div class="skel" style="width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0" />

            <div style="flex: 1; min-width: 0">
                <!-- Header -->
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px">
                    <div class="skel" style="width: 110px; height: 12px; border-radius: 6px" />
                    <div class="skel" style="width: 64px; height: 10px; border-radius: 6px" />
                </div>

                <!-- Content lines -->
                <div class="skel" style="width: 100%; height: 12px; border-radius: 6px; margin-bottom: 8px" />
                <div class="skel" style="width: 85%; height: 12px; border-radius: 6px; margin-bottom: 8px" />
                <div class="skel" style="width: 60%; height: 12px; border-radius: 6px" />
            </div>
        </div>

        <!-- Reaction bar -->
        <div
            style="
                display: flex;
                margin-top: 14px;
                border-top: 1px solid rgba(0, 154, 199, 0.08);
                padding-top: 12px;
                gap: 12px;
            "
        >
            <div class="skel" style="width: 56px; height: 10px; border-radius: 6px" />
            <div class="skel" style="width: 56px; height: 10px; border-radius: 6px" />
        </div>
    </div>
</template>

<style scoped>
.skel {
    background: linear-gradient(90deg, #e8f0f5 25%, #f4f8fb 50%, #e8f0f5 75%);
    background-size: 200% 100%;
    animation: shimmer 1.4s ease-in-out infinite;
}

@keyframes shimmer {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}
</style>
