<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

defineProps<{
    user: User;
}>();

const { getInitials } = useInitials();
</script>

<template>
    <Avatar class="size-8">
        <AvatarImage v-if="user.avatar_url" :src="user.avatar_url" alt="" />
        <AvatarFallback class="bg-surface-2 text-sm">
            {{ getInitials(user.persona_name) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-semibold">{{ user.persona_name }}</span>
    </div>
</template>
