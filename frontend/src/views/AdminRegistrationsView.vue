<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'

interface User {
  id: number
  username: string
  avatar: string | null
  discordId: string
}

interface Registration {
  id: number
  user: User
  lolRank: string
  isCaptainWilling: boolean
  links: {
    opgg: string | null
    leagueOfGraphs: string | null
    dpm: string | null
  }
  registeredAt: string
}

interface TournamentInfo {
  id: number
  name: string
  maxTeams: number
}

const route = useRoute()
const tournamentId = route.params.id || 1

const tournament = ref<TournamentInfo | null>(null)
const registrations = ref<Registration[]>([])
const totalRegistrations = ref(0)
const isLoading = ref(true)
const errorMsg = ref('')

const fetchRegistrations = async () => {
  try {
    isLoading.value = true
    const response = await fetch(
      `http://localhost:8000/api/tournaments/${tournamentId}/registrations`,
    )
    if (!response.ok) {
      throw new Error('Не удалось загрузить список участников')
    }
    const data = await response.json()
    tournament.value = data.tournament
    registrations.value = data.registrations
    totalRegistrations.value = data.totalRegistrations
  } catch (e: any) {
    errorMsg.value = e.message || 'Ошибка запроса'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchRegistrations()
})
</script>

<template>
  <div class="admin-container">
    <header class="admin-header">
      <div>
        <router-link to="/" class="back-link">← На главную</router-link>
        <h1>Панель Организатора: {{ tournament?.name || 'Загрузка...' }}</h1>
      </div>
      <div class="stats-badge" v-if="tournament">
        Всего заявок: <strong>{{ totalRegistrations }}</strong>
      </div>
    </header>

    <div v-if="isLoading" class="loading-state">Загрузка участников...</div>

    <div v-else-if="errorMsg" class="error-state">
      {{ errorMsg }}
    </div>

    <div v-else class="table-wrapper">
      <table class="registrations-table">
        <thead>
          <tr>
            <th>Игрок</th>
            <th>Ранг LoL</th>
            <th>Капитан</th>
            <th>Аналитика</th>
            <th>Дата регистрации</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="reg in registrations" :key="reg.id">
            <td class="user-cell">
              <img
                :src="reg.user.avatar || 'https://cdn.discordapp.com/embed/avatars/0.png'"
                alt="Avatar"
                class="avatar"
              />
              <div class="user-info">
                <span class="username">{{ reg.user.username }}</span>
                <span class="discord-id">ID: {{ reg.user.discordId }}</span>
              </div>
            </td>
            <td>
              <span class="rank-badge">{{ reg.lolRank || 'Unranked' }}</span>
            </td>
            <td>
              <span :class="['captain-badge', reg.isCaptainWilling ? 'is-willing' : 'not-willing']">
                {{ reg.isCaptainWilling ? '★ Готов' : 'Нет' }}
              </span>
            </td>
            <td class="links-cell">
              <a v-if="reg.links.opgg" :href="reg.links.opgg" target="_blank" class="link-btn opgg"
                >OP.GG</a
              >
              <a
                v-if="reg.links.leagueOfGraphs"
                :href="reg.links.leagueOfGraphs"
                target="_blank"
                class="link-btn log"
                >LoG</a
              >
              <a v-if="reg.links.dpm" :href="reg.links.dpm" target="_blank" class="link-btn dpm"
                >DPM</a
              >
            </td>
            <td class="date-cell">
              {{ new Date(reg.registeredAt).toLocaleString('ru-RU') }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.admin-container {
  max-width: 1200px;
  margin: 2rem auto;
  padding: 1.5rem;
  background: #141c33;
  border: 1px solid #283044;
  border-radius: 12px;
  color: #f5f5f5;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}
.admin-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  border-bottom: 1px solid #283044;
  padding-bottom: 1rem;
}
.back-link {
  color: #7983f5;
  text-decoration: none;
  font-size: 0.9rem;
  display: inline-block;
  margin-bottom: 0.5rem;
}
.back-link:hover {
  text-decoration: underline;
}
.stats-badge {
  background: #1c2744;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  border: 1px solid #283044;
}
.loading-state,
.error-state {
  padding: 2rem;
  text-align: center;
  color: #a9b4c9;
}
.table-wrapper {
  overflow-x: auto;
}
.registrations-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}
.registrations-table th,
.registrations-table td {
  padding: 1rem;
  border-bottom: 1px solid #283044;
}
.registrations-table th {
  background: #0b1020;
  color: #a9b4c9;
  font-weight: 600;
}
.user-cell {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
}
.user-info {
  display: flex;
  flex-direction: column;
}
.username {
  font-weight: 600;
}
.discord-id {
  font-size: 0.8rem;
  color: #a9b4c9;
}
.rank-badge {
  background: #5865f2;
  color: white;
  padding: 0.25rem 0.6rem;
  border-radius: 4px;
  font-weight: 600;
  font-size: 0.85rem;
}
.captain-badge {
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.85rem;
}
.captain-badge.is-willing {
  background: rgba(46, 204, 113, 0.2);
  color: #2ecc71;
  border: 1px solid rgba(46, 204, 113, 0.4);
}
.captain-badge.not-willing {
  background: rgba(255, 255, 255, 0.05);
  color: #a9b4c9;
}
.links-cell {
  display: flex;
  gap: 0.4rem;
}
.link-btn {
  padding: 0.3rem 0.6rem;
  border-radius: 4px;
  text-decoration: none;
  font-weight: bold;
  font-size: 0.75rem;
  color: white;
}
.link-btn.opgg {
  background: #5383e8;
}
.link-btn.log {
  background: #e84057;
}
.link-btn.dpm {
  background: #8b5cf6;
}
.link-btn:hover {
  opacity: 0.85;
}
</style>
