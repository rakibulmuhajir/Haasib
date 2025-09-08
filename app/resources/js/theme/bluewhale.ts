// PrimeVue v4 styled mode token extension for Blue Whale theme
// Minimal extension: brand primary + surface/text ramps for light/dark.

const blueWhale = {
  primitive: {
    blue: {
      50: '#eff6ff',
      100: '#dbeafe',
      200: '#bfdbfe',
      300: '#93c5fd',
      400: '#60a5fa',
      500: '#3b82f6',
      600: '#2563eb',
      700: '#1d4ed8',
      800: '#1e40af',
      900: '#1e3a8a',
      950: '#172554',
    },
  },
  semantic: {
    primary: {
      color: '{blue.500}',
      hoverColor: '{blue.600}',
      activeColor: '{blue.700}',
      contrastColor: '#ffffff',
    },
    colorScheme: {
      light: {
        // Ensure primary in light scheme uses blue ramp
        primary: {
          color: '{blue.500}',
          hoverColor: '{blue.600}',
          activeColor: '{blue.700}',
          contrastColor: '#ffffff',
        },
        surface: {
          0: '#ffffff',
          50: 'color-mix(in srgb, {blue.950}, white 95%)',
          100: 'color-mix(in srgb, {blue.950}, white 90%)',
          200: 'color-mix(in srgb, {blue.950}, white 80%)',
          300: 'color-mix(in srgb, {blue.950}, white 70%)',
          400: 'color-mix(in srgb, {blue.950}, white 60%)',
          500: 'color-mix(in srgb, {blue.950}, white 50%)',
          600: 'color-mix(in srgb, {blue.950}, white 40%)',
          700: 'color-mix(in srgb, {blue.950}, white 30%)',
          800: 'color-mix(in srgb, {blue.950}, white 20%)',
          900: 'color-mix(in srgb, {blue.950}, white 10%)',
          950: 'color-mix(in srgb, {blue.950}, white 5%)',
        },
        text: {
          color: '{surface.700}',
          hoverColor: '{surface.800}',
          mutedColor: '{surface.500}',
        },
      },
      dark: {
        // Ensure primary in dark scheme uses blue ramp
        primary: {
          color: '{blue.500}',
          hoverColor: '{blue.600}',
          activeColor: '{blue.700}',
          contrastColor: '#ffffff',
        },
        surface: {
          0: 'rgba(255, 255, 255, 0.08)',
          50: 'rgba(255, 255, 255, 0.04)',
          100: 'rgba(255, 255, 255, 0.08)',
          200: 'rgba(255, 255, 255, 0.12)',
          300: 'rgba(255, 255, 255, 0.16)',
          400: 'rgba(255, 255, 255, 0.24)',
          500: 'rgba(255, 255, 255, 0.32)',
          600: 'rgba(255, 255, 255, 0.40)',
          700: 'rgba(255, 255, 255, 0.52)',
          800: 'rgba(255, 255, 255, 0.64)',
          900: 'rgba(255, 255, 255, 0.76)',
          950: 'rgba(255, 255, 255, 0.88)',
        },
        text: {
          color: 'rgba(255, 255, 255, 0.92)',
          hoverColor: 'rgba(255, 255, 255, 1)',
          mutedColor: 'rgba(255, 255, 255, 0.48)',
        },
      },
    },
  },
  components: {
    button: {
      background: '{primary.color}',
      hoverBackground: '{primary.hoverColor}',
      activeBackground: '{primary.activeColor}',
      color: '{primary.contrastColor}',
    },
    card: {
      background: '{surface.0}',
      color: '{text.color}',
      borderColor: '{surface.200}',
    },
    toolbar: {
      background: '{surface.0}',
      color: '{text.color}',
      borderColor: '{surface.200}',
    },
    datatable: {
      header: {
        background: '{surface.0}',
        color: '{text.color}',
        borderColor: '{surface.200}',
      },
      body: {
        row: {
          hoverBackground: '{surface.100}',
        },
        cell: {
          borderColor: '{surface.200}',
        },
      },
    },
  },
}

export default blueWhale
