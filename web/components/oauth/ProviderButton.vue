<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { OAuthProviderName } from '../../api/types'
import { getProviderConfig } from '../../api/userOAuthApi'

// Define props
interface Props {
  /** OAuth provider name */
  provider: OAuthProviderName
  /** Button style variant */
  variant?: 'filled' | 'outlined' | 'text'
  /** Button size */
  size?: 'large' | 'default' | 'small'
  /** Loading state */
  loading?: boolean
  /** Disabled state */
  disabled?: boolean
  /** Custom button text (overrides default) */
  text?: string
  /** Show icon only (no text) */
  iconOnly?: boolean
  /** Custom width */
  width?: string
  /** Action type for different contexts */
  action?: 'bind' | 'unbind' | 'login' | 'connect'
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'filled',
  size: 'default',
  loading: false,
  disabled: false,
  iconOnly: false,
  action: 'bind',
})

const emit = defineEmits<Emits>()

// Define emits
interface Emits {
  (e: 'click', provider: OAuthProviderName): void
}

// Composables
const { t } = useI18n()

// Get provider configuration
const providerConfig = computed(() => getProviderConfig(props.provider))

// Compute button type based on variant
const buttonType = computed(() => {
  if (props.variant === 'outlined') { return 'default' }
  if (props.variant === 'text') { return 'text' }
  return 'primary'
})

// Compute button text
const buttonText = computed(() => {
  if (props.iconOnly) { return '' }
  if (props.text) { return props.text }

  const providerName = props.provider

  const actionTexts = {
    bind: t('oauth2.personal.actions.bind', { provider: providerName }),
    unbind: t('oauth2.personal.actions.unbind', { provider: providerName }),
    login: t('oauth2.personal.actions.login', { provider: providerName }),
    connect: t('oauth2.personal.actions.connect', { provider: providerName }),
  }

  return actionTexts[props.action]
})

// Compute dynamic button styles
const buttonStyle = computed(() => {
  const config = providerConfig.value
  const styles: Record<string, string> = {}

  if (props.width) {
    styles.width = props.width
  }

  if (props.variant === 'filled') {
    styles.backgroundColor = config.brand_color
    styles.borderColor = config.brand_color
    styles.color = '#ffffff'
  }
  else if (props.variant === 'outlined') {
    styles.borderColor = config.brand_color
    styles.color = config.brand_color
    styles.backgroundColor = 'transparent'
  }
  else if (props.variant === 'text') {
    styles.color = config.brand_color
  }

  return styles
})

// Compute button classes
const buttonClass = computed(() => {
  const classes = ['oauth-provider-button', `oauth-provider-${props.provider}`]

  if (props.iconOnly) {
    classes.push('oauth-provider-button--icon-only')
  }

  classes.push(`oauth-provider-button--${props.variant}`)

  return classes
})

// Icon component mapping - these should be defined in your icon system
const iconComponents = {
  dingtalk: 'IconDingTalk',
  github: 'IconGitHub',
  gitee: 'IconGitee',
  feishu: 'IconFeishu',
  wechat: 'IconWechat',
  qq: 'IconQQ',
}

const iconComponent = computed(() => {
  return iconComponents[props.provider] || 'IconOAuth'
})

// Handle button click
function handleClick() {
  if (!props.loading && !props.disabled) {
    emit('click', props.provider)
  }
}
</script>

<template>
  <el-button
    :type="buttonType"
    :size="size"
    :loading="loading"
    :disabled="disabled"
    :style="buttonStyle"
    :class="buttonClass"
    @click="handleClick"
  >
    <template #icon>
      <el-icon v-if="!loading">
        <component :is="iconComponent" />
      </el-icon>
    </template>
    <span class="provider-button-text">{{ buttonText }}</span>
  </el-button>
</template>

<style lang="scss" scoped>
.oauth-provider-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-weight: 500;
  transition: all 0.2s ease-in-out;
  border-radius: 6px;

  &:hover:not(.is-disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  }

  &--filled {
    &:hover:not(.is-disabled) {
      opacity: 0.9;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
  }

  &--outlined {
    background-color: transparent !important;

    &:hover:not(.is-disabled) {
      background-color: rgba(var(--el-color-primary-rgb), 0.05) !important;
    }
  }

  &--text {
    background-color: transparent !important;
    border: none !important;

    &:hover:not(.is-disabled) {
      background-color: rgba(var(--el-color-primary-rgb), 0.05) !important;
    }
  }

  &--icon-only {
    aspect-ratio: 1;
    padding: 8px;

    .provider-button-text {
      display: none;
    }
  }

  // Provider-specific hover effects
  &.oauth-provider-dingtalk:hover:not(.is-disabled) {
    box-shadow: 0 4px 12px rgba(0, 137, 255, 0.2);
  }

  &.oauth-provider-github:hover:not(.is-disabled) {
    box-shadow: 0 4px 12px rgba(51, 51, 51, 0.2);
  }

  &.oauth-provider-gitee:hover:not(.is-disabled) {
    box-shadow: 0 4px 12px rgba(199, 28, 39, 0.2);
  }

  &.oauth-provider-feishu:hover:not(.is-disabled) {
    box-shadow: 0 4px 12px rgba(0, 212, 170, 0.2);
  }

  &.oauth-provider-wechat:hover:not(.is-disabled) {
    box-shadow: 0 4px 12px rgba(7, 193, 96, 0.2);
  }

  &.oauth-provider-qq:hover:not(.is-disabled) {
    box-shadow: 0 4px 12px rgba(18, 183, 245, 0.2);
  }
}

.provider-button-text {
  font-size: inherit;
  line-height: 1;
}

// Loading state improvements
.oauth-provider-button.is-loading {
  pointer-events: none;
}

// Disabled state
.oauth-provider-button.is-disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

// Mobile responsiveness
@media (max-width: 768px) {
  .oauth-provider-button {
    font-size: 14px;
    padding: 8px 16px;

    &--icon-only {
      padding: 6px;
    }
  }
}

// Dark mode support
@media (prefers-color-scheme: dark) {
  .oauth-provider-button--outlined:hover:not(.is-disabled),
  .oauth-provider-button--text:hover:not(.is-disabled) {
    background-color: rgba(var(--el-color-primary-rgb), 0.1) !important;
  }
}
</style>
