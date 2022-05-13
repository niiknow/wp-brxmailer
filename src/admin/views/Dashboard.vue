<template>
  <div class="app-dashboard">
    <section class="w-full text-center pt-40" v-if="hasLoaded">
      <h3 v-if="pluginUrl.length > 1">
        <b>WP MAIL SMTP</b> was not found.
        To install, follow this link <a :href="pluginUrl">{{ pluginUrl }}</a>
      </h3>
      <h3 v-else>
        Once activated, this plugin is <b>enabled</b> by default.  You must goto <a href="/wp-admin/admin.php?page=wp-mail-smtp">WP MAIL SMTP settings</a> to setup "API Key" for the BrickInc Mailer.  Simply deactivate this plugin if you no longer wish to utilize the BrickInc Mailer.
      </h3>
    </section>
  </div>
</template>

<script>
import { defineComponent, reactive, computed, ref, nextTick, toRaw } from 'vue'

export default defineComponent({
  name: 'Dashboard',
  setup () {
    const hasLoaded = ref(false)
    const pluginUrl = ref('')

    return {
      hasLoaded,
      pluginUrl
    }
  },
  methods: {
    async doLoad() {
      await nextTick()

      // @ts-ignore
      const config = this.$win.vue_wp_plugin_config_admin

      this.pluginUrl = config.wp_mail_smtp_url || '';
      this.hasLoaded = true

      this.$forceUpdate()
    }
  },
  beforeMount() {
    var that = this

    // @ts-ignore
    if (that.$win && that.$win.vue_wp_plugin_config_admin) {
      that.doLoad()
      return
    }

    document.onreadystatechange = async () => {
      if (document.readyState == "complete") {
        this.doLoad()
      }
    }
  }
})
</script>

<!-- Add "scoped" attribute to limit CSS to this component only -->
<style scoped>
</style>
