<script setup lang="ts">
// ============================================================================
// 1. ИМПОРТЫ И ИНИЦИАЛИЗА СТОРОВ
// ============================================================================
import { ref, onMounted } from 'vue'
// Вариант 2: Через алиас Vite (@ указывающий на src/)
import { useAuthStore } from '@/stores/auth'
const authStore = useAuthStore()

// ============================================================================
// 2. ИНТЕРФЕЙСЫ И ТИПЫ ДАННЫХ
// ============================================================================
interface Tournament {
  id: number
  name: string
  format: string
  status: string
  maxTeams: number
  registeredTeams?: number
  startDate: string | null
}

// ============================================================================
// 3. РЕАКТИВНОЕ СОСТОЯНИЕ (STATE)
// ============================================================================
// Список турниров
const tournaments = ref<Tournament[]>([])

// Модальное окно создания турнира (Для Администратора)
const showCreateModal = ref(false)
const newTournament = ref({
  name: '',
  format: '5v5',
  maxTeams: 16,
  startDate: '',
})

// Модальное окно регистрации участника (Для игроков)
const showRegisterModal = ref(false)
const selectedTournament = ref<Tournament | null>(null)
const registerForm = ref({
  riotId: '',
  region: 'euw1',
  isCaptainWilling: false,
})

// ============================================================================
// 4. МЕТОДЫ ВЗАИМОДЕЙСТВИЯ С API
// ============================================================================

/**
 * 4.1 Получение списка текущих турниров
 */
async function fetchTournaments() {
  try {
    const res = await fetch('http://localhost:8000/api/tournaments', { credentials: 'include' })
    if (res.ok) {
      tournaments.value = await res.json()
    }
  } catch (e) {
    console.error('Ошибка загрузки турниров:', e)
  }
}

/**
 * 4.2 Создание турнира (Доступно только ROLE_ADMIN)
 */
async function createTournament() {
  try {
    const res = await fetch('http://localhost:8000/api/tournaments', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(newTournament.value),
    })

    if (res.ok) {
      showCreateModal.value = false
      newTournament.value = { name: '', format: '5v5', maxTeams: 16, startDate: '' }
      await fetchTournaments()
    } else {
      const err = await res.json()
      alert(err.error || 'Ошибка при создании турнира')
    }
  } catch (e) {
    console.error('Ошибка создания турнира:', e)
  }
}

/**
 * 4.3 Открытие модалки и регистрация участника по Riot ID
 */
function openRegisterModal(tournament: Tournament) {
  selectedTournament.value = tournament
  registerForm.value = { riotId: '', region: 'euw1', isCaptainWilling: false }
  showRegisterModal.value = true
}

async function submitRegistration() {
  if (!selectedTournament.value) return

  try {
    const res = await fetch(
      `http://localhost:8000/api/tournaments/${selectedTournament.value.id}/register`,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(registerForm.value),
      },
    )

    const data = await res.json()

    if (res.ok) {
      alert(`Успешно! Подтвержден ранг: ${data.verifiedData.rankDisplay}`)
      showRegisterModal.value = false
      await fetchTournaments()
    } else {
      alert(data.error || 'Ошибка регистрации')
    }
  } catch (e) {
    console.error('Ошибка регистрации:', e)
  }
}

/**
 * 4.4 Dev-метод быстрой смены роли текущего пользователя
 */
async function toggleDevAdmin() {
  try {
    const res = await fetch('http://localhost:8000/api/dev/toggle-admin', {
      method: 'POST',
      credentials: 'include',
    })
    if (res.ok) {
      await authStore.fetchUser()
    }
  } catch (e) {
    console.error('Ошибка переключения роли:', e)
  }
}

// ============================================================================
// БУДУЩИЕ ФУНКЦИИ (PLACEHOLDERS):
// - async function fetchDraftState(tournamentId) -> Загрузка порядка пиков драфта
// - async function pickPlayer(playerId)           -> Выбор игрока капитаном
// - async function assignCaptains(tournamentId)   -> Админ-назначение капитанов
// ============================================================================

// ============================================================================
// 5. ЖИЗНЕННЫЙ ЦИКЛ КОМПОНЕНТА
// ============================================================================
onMounted(() => {
  authStore.fetchUser()
  fetchTournaments()
})
</script>

<template>
  <div class="app">
    <!-- ================================================================== -->
    <!-- 1. ЭКРАН ЗАГРУЗКИ СЕССИИ (LOADER)                                 -->
    <!-- ================================================================== -->
    <div v-if="authStore.isLoading" class="loader-screen">
      <div class="spinner"></div>
      <p>Загрузка платформы...</p>
    </div>

    <!-- ================================================================== -->
    <!-- 2. ЭКРАН НЕАВТОРИЗОВАННОГО ПОЛЬЗОВАТЕЛЯ (GUEST LANDING)            -->
    <!-- ================================================================== -->
    <template v-else-if="!authStore.isAuthenticated">
      <header class="header">
        <a class="logo" href="/">LoL Tournaments</a>
        <button class="discord-button" @click="authStore.login">Войти через Discord</button>
      </header>

      <main class="guest-hero">
        <div class="hero-content">
          <span class="hero-badge">Discord Community League</span>
          <h1>Любительские турниры League of Legends</h1>
          <p>
            Участвуй в регулярных 5v5 и 1v1 турнирах, собирай команды, поднимайся в рейтинге и
            соревнуйся с игроками нашего сообщества.
          </p>

          <button class="discord-button-large" @click="authStore.login">
            <svg class="discord-icon" viewBox="0 0 127.14 96.36" width="24" height="24">
              <path
                fill="currentColor"
                d="M107.7 8.07A105.15 105.15 0 0 0 81.47 0a72.06 72.06 0 0 0-3.36 6.83 97.68 97.68 0 0 0-29.11 0A72.37 72.37 0 0 0 45.64 0a105.89 105.89 0 0 0-26.25 8.09C2.79 32.65-1.71 56.6.54 80.21a105.73 105.73 0 0 0 32.17 16.15 77.7 77.7 0 0 0 6.89-11.11 68.42 68.42 0 0 1-10.85-5.18c.91-.66 1.8-1.34 2.66-2a75.57 75.57 0 0 0 64.32 0c.87.68 1.75 1.36 2.66 2a68.68 68.68 0 0 1-10.87 5.19 77 77 0 0 0 6.89 11.1 105.25 105.25 0 0 0 32.19-16.14c2.64-27.38-4.51-51.11-18.91-72.15zM42.45 65.69c-6.32 0-11.53-5.8-11.53-12.9 0-7.1 5.09-12.9 11.53-12.9 6.48 0 11.63 5.84 11.53 12.9 0 7.1-5.05 12.9-11.53 12.9zm42.24 0c-6.32 0-11.53-5.8-11.53-12.9 0-7.1 5.09-12.9 11.53-12.9 6.48 0 11.63 5.84 11.53 12.9 0 7.1-5.05 12.9-11.53 12.9z"
              />
            </svg>
            Авторизоваться через Discord
          </button>
        </div>

        <div class="features-grid">
          <div class="feature-card">
            <div class="feature-icon">🏆</div>
            <h3>Разнообразие форматов</h3>
            <p>5v5 Summoner's Rift и 1v1 ARAM турниры для любого уровня игры.</p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">🛡️</div>
            <h3>Быстрая регистрация</h3>
            <p>Вход в один клик через Discord без подтверждения почты и сложных форм.</p>
          </div>
          <div class="feature-card">
            <div class="feature-icon">⚡</div>
            <h3>Автоматические сетки</h3>
            <p>Генерация турнирных сеток и удобный ввод результатов матчей.</p>
          </div>
        </div>
      </main>
    </template>

    <!-- ================================================================== -->
    <!-- 3. ОСНОВНОЙ ДАШБОРД (DASHBOARD - АВТОРИЗОВАН)                     -->
    <!-- ================================================================== -->
    <template v-else>
      <!-- 3.1 ШАПКА И ПРОФИЛЬ -->
      <header class="header">
        <a class="logo" href="/">LoL Tournaments</a>

        <nav class="navigation">
          <a href="#" class="active">Главная</a>
          <a href="#tournaments">Турниры</a>
          <a href="#my-teams">Мои команды</a>
        </nav>

        <div class="user-profile">
          <img :src="authStore.avatarUrl" alt="Avatar" class="user-avatar" />
          <span class="user-name">{{ authStore.user?.username }}</span>
          <span v-if="authStore.isAdmin" class="admin-badge">Admin</span>

          <!-- Dev-переключатель роли -->
          <button class="dev-toggle-btn" @click="toggleDevAdmin">
            {{ authStore.isAdmin ? 'Снять Admin' : 'Дать Admin' }}
          </button>

          <button class="logout-button" @click="authStore.logout">Выйти</button>
        </div>
      </header>

      <!-- 3.2 ОСНОВНОЙ КОНТЕНТ -->
      <main class="dashboard-main">
        <!-- Герой-блок -->
        <section class="dashboard-hero">
          <div>
            <h2>Добро пожаловать, {{ authStore.user?.username }}!</h2>
            <p>Выбирай доступный турнир или подавай заявку на участие в драфте.</p>
          </div>

          <button v-if="authStore.isAdmin" class="primary-button" @click="showCreateModal = true">
            + Создать турнир
          </button>
        </section>

        <!-- 3.3 СЕТКА / СПИСОК ТУРНИРОВ -->
        <section id="tournaments" class="tournaments-section">
          <div class="section-header">
            <h2>Текущие и предстоящие турниры</h2>
          </div>

          <!-- Пустой список -->
          <div v-if="tournaments.length === 0" class="empty-state">
            <div class="empty-icon">⚔️</div>
            <h3>Турниров пока нет</h3>
            <p v-if="authStore.isAdmin">
              Организаторы ещё не создали турниры. Нажмите «+ Создать турнир» выше!
            </p>
            <p v-else>
              Организаторы ещё не запланировали новые матчи. Следите за анонсами в Discord!
            </p>
          </div>

          <!-- Список доступных турниров -->
          <div v-else class="tournaments-grid">
            <div v-for="t in tournaments" :key="t.id" class="tournament-card">
              <div class="card-header">
                <span class="format-tag">{{ t.format }}</span>
                <span class="status-tag" :class="t.status">{{ t.status }}</span>
              </div>

              <h3 class="tournament-title">{{ t.name }}</h3>

              <div class="tournament-details">
                <p><strong>Участников:</strong> {{ t.registeredTeams ?? 0 }} / {{ t.maxTeams }}</p>
                <p v-if="t.startDate"><strong>Дата старта:</strong> {{ t.startDate }}</p>
              </div>

              <!-- Кнопка вызова модального окна регистрации -->
              <button class="join-button" @click="openRegisterModal(t)">Зарегистрироваться</button>

              <router-link
                v-if="authStore.isAdmin"
                :to="`/admin/tournaments/${t.id}/registrations`"
                class="admin-link-button"
              >
                📋 Список участников (Admin)
              </router-link>
            </div>
          </div>
        </section>

        <!-- ============================================================== -->
        <!-- БУДУЩИЕ СЕКЦИИ (PLACEHOLDERS):                                  -->
        <!-- 3.4 <section id="draft-room">  <- КОМНАТА ДРАФТА В РЕАЛЬНОМ ВРЕМЕНИ -->
        <!-- 3.5 <section id="bracket">     <- СЕТКА ТУРНИРА (SINGLE ELIMINATION) -->
        <!-- ============================================================== -->
      </main>

      <!-- ================================================================ -->
      <!-- 4. МОДАЛЬНЫЕ ОКНА                                                -->
      <!-- ================================================================ -->

      <!-- 4.1 МОДАЛКА СОЗДАНИЯ ТУРНИРА (ТОЛЬКО ДЛЯ АДМИНИСТРАТОРА) -->
      <div v-if="showCreateModal" class="modal-backdrop" @click.self="showCreateModal = false">
        <div class="modal">
          <div class="modal-header">
            <h3>Создание нового турнира</h3>
            <button class="close-btn" @click="showCreateModal = false">&times;</button>
          </div>

          <form @submit.prevent="createTournament" class="modal-form">
            <div class="form-group">
              <label>Название турнира</label>
              <input
                v-model="newTournament.name"
                type="text"
                required
                placeholder="например, Summer Draft Cup #1"
                class="form-input"
              />
            </div>

            <div class="form-group">
              <label>Формат</label>
              <select v-model="newTournament.format" class="form-select">
                <option value="5v5">5v5 Summoner's Rift (Solo Draft)</option>
                <option value="1v1">1v1 ARAM</option>
              </select>
            </div>

            <div class="form-group">
              <label>Максимум участников</label>
              <input
                v-model.number="newTournament.maxTeams"
                type="number"
                min="2"
                max="100"
                class="form-input"
              />
            </div>

            <div class="form-group">
              <label>Дата и время начала</label>
              <input v-model="newTournament.startDate" type="datetime-local" class="form-input" />
            </div>

            <div class="modal-actions">
              <button type="button" class="cancel-button" @click="showCreateModal = false">
                Отмена
              </button>
              <button type="submit" class="primary-button">Создать</button>
            </div>
          </form>
        </div>
      </div>

      <!-- 4.2 МОДАЛКА РЕГИСТРАЦИИ НА ТУРНИР ПО RIOT ID (ДЛЯ ИГРОКОВ) -->
      <div
        v-if="showRegisterModal && selectedTournament"
        class="modal-backdrop"
        @click.self="showRegisterModal = false"
      >
        <div class="modal">
          <div class="modal-header">
            <h3>Регистрация на {{ selectedTournament.name }}</h3>
            <button class="close-btn" @click="showRegisterModal = false">&times;</button>
          </div>

          <form @submit.prevent="submitRegistration" class="modal-form">
            <div class="form-group">
              <label>Ваш Riot ID (GameName#TagLine)</label>
              <input
                v-model="registerForm.riotId"
                type="text"
                required
                placeholder="например, Rayden#EUW или Faker#KR1"
                class="form-input"
              />
              <span class="form-hint">Мы автоматически проверим ранг через Riot API</span>
            </div>

            <div class="form-group">
              <label>Сервер основного аккаунта</label>
              <select v-model="registerForm.region" class="form-select">
                <option value="euw1">Europe West (EUW)</option>
                <option value="eun1">Europe Nordic & East (EUNE)</option>
                <option value="ru">Russia (RU)</option>
                <option value="tr1">Turkey (TR)</option>
                <option value="na1">North America (NA)</option>
                <option value="kr">Korea (KR)</option>
              </select>
            </div>

            <div class="form-group checkbox-group">
              <label class="checkbox-label">
                <input type="checkbox" v-model="registerForm.isCaptainWilling" />
                <span>Готов быть капитаном команды на драфте</span>
              </label>
            </div>

            <div class="modal-actions">
              <button type="button" class="cancel-button" @click="showRegisterModal = false">
                Отмена
              </button>
              <button type="submit" class="primary-button">Подтвердить участие</button>
            </div>
          </form>
        </div>
      </div>

      <!-- ================================================================ -->
      <!-- БУДУЩИЕ МОДАЛЬНЫЕ ОКНА:                                          -->
      <!-- 4.3 <div v-if="showCaptainAssignModal"> <- Назначение 10 капитанов  -->
      <!-- ================================================================ -->
    </template>
  </div>
</template>

<style scoped>
/* ============================================================================
 * 1. БАЗОВЫЕ СТИЛИ И ОБЩИЙ ЛЕЙАУТ
 * ============================================================================ */
.app {
  min-height: 100vh;
  background: radial-gradient(circle at 50% 10%, #141c33 0%, #0b1020 70%);
  color: #f5f5f5;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Экраны загрузки */
.loader-screen {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  color: #a9b4c9;
}
.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid rgba(255, 255, 255, 0.1);
  border-radius: 50%;
  border-top-color: #5865f2;
  animation: spin 1s ease-in-out infinite;
  margin-bottom: 16px;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* ============================================================================
 * 2. ШАПКА (HEADER) И НАВИГАЦИЯ
 * ============================================================================ */
.header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 8%;
  border-bottom: 1px solid rgba(40, 48, 68, 0.7);
  backdrop-filter: blur(8px);
}

.logo {
  color: #d4af65;
  font-size: 22px;
  font-weight: 700;
  text-decoration: none;
  letter-spacing: 0.5px;
}

.navigation {
  display: flex;
  gap: 24px;
}

.navigation a {
  color: #a9b4c9;
  text-decoration: none;
  font-size: 14px;
  transition: color 0.2s;
}

.navigation a:hover,
.navigation a.active {
  color: #f5f5f5;
}

/* Профиль пользователя и Dev-инструменты */
.user-profile {
  display: flex;
  align-items: center;
  gap: 10px;
  background: rgba(24, 32, 53, 0.8);
  padding: 6px 14px 6px 8px;
  border-radius: 20px;
  border: 1px solid rgba(212, 175, 101, 0.3);
}

.user-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
}

.user-name {
  font-weight: 600;
  font-size: 14px;
}

.admin-badge {
  background: rgba(212, 175, 101, 0.2);
  color: #d4af65;
  border: 1px solid rgba(212, 175, 101, 0.4);
  font-size: 10px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  text-transform: uppercase;
}

.dev-toggle-btn {
  background: rgba(88, 101, 242, 0.2);
  border: 1px solid #5865f2;
  color: #5865f2;
  padding: 4px 8px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 11px;
  font-weight: 600;
  transition: all 0.2s;
}
.dev-toggle-btn:hover {
  background: #5865f2;
  color: #fff;
}

.logout-button {
  background: transparent;
  border: 1px solid #3f4a61;
  color: #a0aec0;
  padding: 4px 8px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
  margin-left: 6px;
}
.logout-button:hover {
  border-color: #e53e3e;
  color: #fc8181;
}

/* Кнопки действия */
.discord-button,
.primary-button {
  background: #5865f2;
  color: white;
  border: 0;
  padding: 10px 18px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  font-size: 14px;
  transition:
    background-color 0.2s,
    transform 0.1s;
}
.discord-button:hover,
.primary-button:hover {
  background: #4752c4;
}

.admin-link-button {
  margin-top: 8px;
  display: block;
  text-align: center;
  background: rgba(88, 101, 242, 0.15);
  border: 1px solid #5865f2;
  color: #7983f5;
  padding: 8px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.2s;
}
.admin-link-button:hover {
  background: #5865f2;
  color: white;
}

/* ============================================================================
 * 3. ГОСТЕВОЙ ЭКРАН (GUEST HERO)
 * ============================================================================ */
.guest-hero {
  padding: 80px 8% 60px;
  text-align: center;
}

.hero-badge {
  display: inline-block;
  background: rgba(212, 175, 101, 0.15);
  color: #d4af65;
  border: 1px solid rgba(212, 175, 101, 0.3);
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  margin-bottom: 24px;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.hero-content h1 {
  font-size: 46px;
  font-weight: 800;
  margin-bottom: 20px;
  line-height: 1.2;
}

.hero-content p {
  color: #a9b4c9;
  max-width: 600px;
  margin: 0 auto 36px;
  font-size: 18px;
  line-height: 1.6;
}

.discord-button-large {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  background: #5865f2;
  color: white;
  border: 0;
  padding: 16px 32px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 700;
  font-size: 16px;
  box-shadow: 0 4px 20px rgba(88, 101, 242, 0.4);
  transition: all 0.2s;
}
.discord-button-large:hover {
  background: #4752c4;
  transform: translateY(-2px);
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 24px;
  margin-top: 90px;
}

.feature-card {
  background: rgba(18, 25, 43, 0.6);
  border: 1px solid rgba(40, 48, 68, 0.8);
  padding: 28px 20px;
  border-radius: 12px;
  text-align: left;
}

/* ============================================================================
 * 4. ДАШБОРД И КАРТОЧКИ ТУРНИРОВ
 * ============================================================================ */
.dashboard-main {
  padding: 40px 8%;
}

.dashboard-hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: rgba(18, 25, 43, 0.6);
  border: 1px solid rgba(40, 48, 68, 0.8);
  padding: 32px;
  border-radius: 12px;
  margin-bottom: 40px;
}

.tournaments-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
}

.tournament-card {
  background: rgba(18, 25, 43, 0.8);
  border: 1px solid rgba(40, 48, 68, 0.9);
  border-radius: 10px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  transition:
    transform 0.2s,
    border-color 0.2s;
}
.tournament-card:hover {
  transform: translateY(-3px);
  border-color: rgba(212, 175, 101, 0.5);
}

.card-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 12px;
}

.format-tag {
  background: rgba(88, 101, 242, 0.2);
  color: #7983f5;
  font-size: 12px;
  font-weight: 700;
  padding: 4px 8px;
  border-radius: 4px;
}

.status-tag {
  font-size: 12px;
  font-weight: 600;
  padding: 4px 8px;
  border-radius: 4px;
  background: rgba(46, 204, 113, 0.15);
  color: #2ecc71;
  text-transform: capitalize;
}

.join-button {
  margin-top: 16px;
  width: 100%;
  background: transparent;
  border: 1px solid #d4af65;
  color: #d4af65;
  padding: 10px;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
}
.join-button:hover {
  background: #d4af65;
  color: #0b1020;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  background: rgba(15, 21, 37, 0.4);
  border: 1px dashed rgba(40, 48, 68, 0.9);
  border-radius: 12px;
}

/* ============================================================================
 * 5. МОДАЛЬНЫЕ ОКНА И ПОЛЯ ФОРМ
 * ============================================================================ */
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal {
  background: #141c33;
  border: 1px solid #283044;
  border-radius: 12px;
  width: 100%;
  max-width: 450px;
  padding: 24px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.close-btn {
  background: transparent;
  border: 0;
  color: #a9b4c9;
  font-size: 24px;
  cursor: pointer;
}

.modal-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-size: 13px;
  color: #a9b4c9;
}

.form-hint {
  font-size: 11px;
  color: #7983f5;
}

.form-input,
.form-select {
  background: #0b1020;
  border: 1px solid #283044;
  border-radius: 6px;
  padding: 10px 12px;
  color: #f5f5f5;
  font-size: 14px;
  outline: none;
}
.form-input:focus,
.form-select:focus {
  border-color: #5865f2;
}

/* Стили чекбокса */
.checkbox-group {
  margin-top: 4px;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  font-size: 13px;
  color: #f5f5f5;
}

.checkbox-label input[type='checkbox'] {
  width: 16px;
  height: 16px;
  accent-color: #5865f2;
  cursor: pointer;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 12px;
}

.cancel-button {
  background: transparent;
  border: 1px solid #283044;
  color: #a9b4c9;
  padding: 10px 16px;
  border-radius: 6px;
  cursor: pointer;
}
.cancel-button:hover {
  background: rgba(255, 255, 255, 0.05);
}

/* ============================================================================
 * 6. БУДУЩИЕ СТИЛИ (PLACEHOLDERS):
 * - Стили драфт-сетки и подсветок текущего пика
 * - Стили интерактивных турнирных сеток (Single/Double Elimination)
 * ============================================================================ */
</style>
