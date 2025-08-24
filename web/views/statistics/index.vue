<!--
 - OAuth2 Statistics Dashboard
 -
 - Provides comprehensive statistics and charts for OAuth2 usage
 - Includes provider distribution, binding trends, and performance metrics
 - Uses ECharts for data visualization
-->
<script setup lang="tsx">
import type { BindingStatistics } from '../../api/types'
import { getBindingStatistics, getProviderBrandColor, getProviderDisplayName } from '../../api/oauthApi'
import { useMessage } from '@/hooks/useMessage.ts'

defineOptions({ name: 'oauth2:statistics' })

const msg = useMessage()
const loading = ref(false)
const period = ref<'day' | 'week' | 'month' | 'year'>('month')
const statisticsData = ref<BindingStatistics | null>(null)

// 时间周期选项
const periodOptions = [
  { label: '近7天', value: 'day' },
  { label: '近4周', value: 'week' },
  { label: '近12月', value: 'month' },
  { label: '近5年', value: 'year' },
]

// 获取统计数据
async function fetchStatistics() {
  loading.value = true
  try {
    const response = await getBindingStatistics(period.value)
    statisticsData.value = response.data
  }
  catch (error: any) {
    msg.error(error.message || '获取统计数据失败')
  }
  finally {
    loading.value = false
  }
}

// 提供者分布图表配置
const providerDistributionOption = computed(() => {
  if (!statisticsData.value?.provider_distribution) { return null }

  return {
    title: {
      text: '提供者分布',
      left: 'center',
    },
    tooltip: {
      trigger: 'item',
      formatter: '{a} <br/>{b} : {c} ({d}%)',
    },
    legend: {
      orient: 'vertical',
      left: 'left',
    },
    series: [
      {
        name: '绑定数量',
        type: 'pie',
        radius: '50%',
        data: statisticsData.value.provider_distribution.map(item => ({
          value: item.count,
          name: getProviderDisplayName(item.provider),
          itemStyle: {
            color: getProviderBrandColor(item.provider),
          },
        })),
        emphasis: {
          itemStyle: {
            shadowBlur: 10,
            shadowOffsetX: 0,
            shadowColor: 'rgba(0, 0, 0, 0.5)',
          },
        },
      },
    ],
  }
})

// 趋势图表配置
const trendOption = computed(() => {
  if (!statisticsData.value?.time_series) { return null }

  return {
    title: {
      text: '绑定趋势',
      left: 'center',
    },
    tooltip: {
      trigger: 'axis',
    },
    legend: {
      data: ['总绑定数', '新增绑定'],
      top: 30,
    },
    xAxis: {
      type: 'category',
      data: statisticsData.value.time_series.map(item => item.date),
    },
    yAxis: {
      type: 'value',
    },
    series: [
      {
        name: '总绑定数',
        type: 'line',
        data: statisticsData.value.time_series.map(item => item.bindings),
        smooth: true,
        lineStyle: {
          color: '#409EFF',
        },
      },
      {
        name: '新增绑定',
        type: 'bar',
        data: statisticsData.value.time_series.map(item => item.new_bindings),
        itemStyle: {
          color: '#67C23A',
        },
      },
    ],
  }
})

// 监听周期变化
watch(period, () => {
  fetchStatistics()
})

// 组件挂载时获取数据
onMounted(() => {
  fetchStatistics()
})
</script>

<template>
  <div v-loading="loading" class="statistics-dashboard">
    <!-- 统计卡片 -->
    <el-row :gutter="20" class="mb-6">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-icon total">
              <i class="i-solar:users-group-two-rounded-bold" />
            </div>
            <div class="stat-info">
              <div class="stat-value">
                {{ statisticsData?.total_bindings || 0 }}
              </div>
              <div class="stat-label">
                总绑定数
              </div>
            </div>
          </div>
        </el-card>
      </el-col>

      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-icon providers">
              <i class="i-solar:shield-network-bold" />
            </div>
            <div class="stat-info">
              <div class="stat-value">
                {{ Object.keys(statisticsData?.active_providers || {}).length }}
              </div>
              <div class="stat-label">
                活跃提供者
              </div>
            </div>
          </div>
        </el-card>
      </el-col>

      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-icon recent">
              <i class="i-solar:clock-circle-bold" />
            </div>
            <div class="stat-info">
              <div class="stat-value">
                {{ statisticsData?.recent_bindings || 0 }}
              </div>
              <div class="stat-label">
                近期新增
              </div>
            </div>
          </div>
        </el-card>
      </el-col>

      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-content">
            <div class="stat-icon growth">
              <i class="i-solar:chart-square-bold" />
            </div>
            <div class="stat-info">
              <div class="stat-value growth-value">
                {{ (statisticsData?.monthly_growth || 0).toFixed(1) }}%
              </div>
              <div class="stat-label">
                月度增长
              </div>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 控制面板 -->
    <el-card class="mb-6">
      <template #header>
        <div class="flex items-center justify-between">
          <span class="font-medium">数据分析</span>
          <div class="flex space-x-3">
            <el-radio-group v-model="period" size="small">
              <el-radio-button
                v-for="option in periodOptions"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </el-radio-button>
            </el-radio-group>
            <el-button size="small" @click="fetchStatistics">
              <i class="i-solar:refresh-outline mr-1" />
              刷新
            </el-button>
          </div>
        </div>
      </template>

      <el-row :gutter="20">
        <!-- 提供者分布 -->
        <el-col :span="12">
          <div class="chart-container">
            <div
              v-if="providerDistributionOption"
              id="provider-chart"
              v-echarts="providerDistributionOption"
              class="chart"
            />
            <el-empty v-else description="暂无分布数据" />
          </div>
        </el-col>

        <!-- 绑定趋势 -->
        <el-col :span="12">
          <div class="chart-container">
            <div
              v-if="trendOption"
              id="trend-chart"
              v-echarts="trendOption"
              class="chart"
            />
            <el-empty v-else description="暂无趋势数据" />
          </div>
        </el-col>
      </el-row>
    </el-card>

    <!-- 提供者详细统计 -->
    <el-card>
      <template #header>
        <span class="font-medium">提供者详细统计</span>
      </template>

      <el-table
        v-if="statisticsData?.provider_distribution"
        :data="statisticsData.provider_distribution"
        style="width: 100%"
      >
        <el-table-column label="提供者" width="200">
          <template #default="{ row }">
            <div class="flex items-center space-x-2">
              <div
                class="h-6 w-6 flex items-center justify-center rounded text-xs text-white font-bold"
                :style="{ backgroundColor: getProviderBrandColor(row.provider) }"
              >
                {{ getProviderDisplayName(row.provider).charAt(0) }}
              </div>
              <span class="font-medium">{{ getProviderDisplayName(row.provider) }}</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column prop="count" label="绑定数量" width="120">
          <template #default="{ row }">
            <el-tag type="primary">
              {{ row.count }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column prop="percentage" label="占比" width="120">
          <template #default="{ row }">
            <div class="flex items-center space-x-2">
              <span>{{ row.percentage.toFixed(1) }}%</span>
              <div class="progress-bar">
                <div
                  class="progress-fill"
                  :style="{
                    width: `${row.percentage}%`,
                    backgroundColor: getProviderBrandColor(row.provider),
                  }"
                />
              </div>
            </div>
          </template>
        </el-table-column>

        <el-table-column prop="growth" label="增长率" width="120">
          <template #default="{ row }">
            <el-tag
              :type="row.growth > 0 ? 'success' : row.growth < 0 ? 'danger' : 'info'"
            >
              {{ row.growth > 0 ? '+' : '' }}{{ row.growth.toFixed(1) }}%
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column label="状态">
          <template #default="{ row }">
            <el-tag v-if="row.count > 0" type="success">
              活跃
            </el-tag>
            <el-tag v-else type="info">
              未使用
            </el-tag>
          </template>
        </el-table-column>
      </el-table>

      <el-empty v-else description="暂无统计数据" />
    </el-card>
  </div>
</template>

<style scoped lang="scss">
.statistics-dashboard {
  padding: 20px;
  background: #f5f5f5;
  min-height: calc(100vh - 120px);
}

.stat-card {
  .stat-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;

    &.total {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    &.providers {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    &.recent {
      background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }

    &.growth {
      background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    }
  }

  .stat-info {
    text-align: right;

    .stat-value {
      font-size: 28px;
      font-weight: bold;
      color: #303133;
      line-height: 1;

      &.growth-value {
        color: #67c23a;
      }
    }

    .stat-label {
      font-size: 14px;
      color: #909399;
      margin-top: 4px;
    }
  }
}

.chart-container {
  height: 400px;

  .chart {
    width: 100%;
    height: 100%;
  }
}

.progress-bar {
  flex: 1;
  height: 6px;
  background: #e4e7ed;
  border-radius: 3px;
  overflow: hidden;

  .progress-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.3s ease;
  }
}

.el-card {
  border: none;
  box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.06);

  :deep(.el-card__header) {
    border-bottom: 1px solid #e4e7ed;
    background: #fafbfc;
  }
}
</style>
