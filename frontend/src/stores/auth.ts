import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export interface User {
  id: number
  discordId: string
  username: string
  avatar: string | null
  roles: string[]
}

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const isAuthenticated = ref<boolean>(false)
  const isLoading = ref<boolean>(true)

  // Динамическая ссылка на аватар пользователя в Discord CDN
  const avatarUrl = computed(() => {
    if (!user.value || !user.value.avatar) {
      return 'https://cdn.discordapp.com/embed/avatars/0.png'
    }
    return `https://cdn.discordapp.com/avatars/${user.value.discordId}/${user.value.avatar}.png`
  })

  const isAdmin = computed(() => {
    return user.value?.roles.includes('ROLE_ADMIN') ?? false
  })

  // Проверка сессии при загрузке приложения
  async function fetchUser() {
    isLoading.value = true
    try {
      const response = await fetch('http://localhost:8000/api/auth/me', {
        credentials: 'include', // Обязательно передаем Cookie сессии!
      })

      if (response.ok) {
        const data = await response.json()
        if (data.authenticated) {
          user.value = data.user
          isAuthenticated.value = true
        } else {
          user.value = null
          isAuthenticated.value = false
        }
      } else {
        user.value = null
        isAuthenticated.value = false
      }
    } catch (e) {
      console.error('Не удалось загрузить данные сессии:', e)
      user.value = null
      isAuthenticated.value = false
    } finally {
      isLoading.value = false
    }
  }

  function login() {
    window.location.href = 'http://localhost:8000/api/auth/discord'
  }

  function logout() {
    user.value = null
    isAuthenticated.value = false
    window.location.href = 'http://localhost:8000/api/auth/logout'
  }

  return {
    user,
    isAuthenticated,
    isLoading,
    isAdmin,
    avatarUrl,
    fetchUser,
    login,
    logout,
  }
})
