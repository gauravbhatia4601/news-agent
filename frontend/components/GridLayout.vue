<template>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
    <!-- Left column (2/3) -->
    <div class="md:col-span-2">
      <slot name="left" />
    </div>

    <!-- Right column (1/3) -->
    <div class="space-y-5">
      <slot name="right" />
    </div>
  </div>
</template>
