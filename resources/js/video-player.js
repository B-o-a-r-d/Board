/**
 * Custom video player (ported from docs/ui_elements/video.html) as a reusable
 * Alpine component taking a single source. Used inside the lightbox for video
 * attachments. Markup lives in resources/views/components/lightbox.blade.php.
 *
 *   <div x-data="videoPlayer(url, mime)" x-ref="videoContainer"> … </div>
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('videoPlayer', (source = '', mime = 'video/mp4') => ({
        source,
        mime,
        playing: false,
        controls: true,
        muted: false,
        fullscreen: false,
        ended: false,
        autoHideControlsDelay: 3000,
        controlsHideTimeout: null,
        poster: null,
        videoDuration: 0,
        timeDurationString: '00:00',
        timeElapsedString: '00:00',
        showTime: false,
        volume: 1,
        volumeBeforeMute: 1,
        videoPlayerReady: false,

        init() {
            this.$refs.player.load()
            this.$refs.player.controls = false

            this.$watch('playing', (value) => {
                if (value) {
                    this.ended = false
                    this.controlsHideTimeout = setTimeout(() => {
                        this.controls = false
                    }, this.autoHideControlsDelay)
                } else {
                    clearTimeout(this.controlsHideTimeout)
                    this.controls = true
                }
            })

            if (! document?.fullscreenEnabled && this.$refs.fullscreenButton) {
                this.$refs.fullscreenButton.style.display = 'none'
            }

            document.addEventListener('fullscreenchange', () => {
                this.fullscreen = !! document.fullscreenElement
            })
        },

        timelineSeek(e) {
            const time = this.formatTime(Math.round(e.target.value))
            this.timeElapsedString = `${time.minutes}:${time.seconds}`
        },

        metaDataLoaded(event) {
            this.videoDuration = event.target.duration
            this.$refs.videoProgress.setAttribute('max', this.videoDuration)

            const time = this.formatTime(Math.round(this.videoDuration))
            this.timeDurationString = `${time.minutes}:${time.seconds}`
            this.showTime = true
            this.videoPlayerReady = true
        },

        togglePlay() {
            if (this.$refs.player.paused || this.$refs.player.ended) {
                this.playing = true
                this.$refs.player.play()
            } else {
                this.$refs.player.pause()
                this.playing = false
            }
        },

        toggleMute() {
            this.muted = ! this.muted
            this.$refs.player.muted = this.muted

            if (this.muted) {
                this.volumeBeforeMute = this.volume
                this.volume = 0
            } else {
                this.volume = this.volumeBeforeMute
                this.$refs.player.volume = this.volume
            }
        },

        timeUpdatedInterval() {
            if (! this.$refs.videoProgress.getAttribute('max')) {
                this.$refs.videoProgress.setAttribute('max', this.$refs.player.duration)
            }
            this.$refs.videoProgress.value = this.$refs.player.currentTime
            const time = this.formatTime(Math.round(this.$refs.player.currentTime))
            this.timeElapsedString = `${time.minutes}:${time.seconds}`
        },

        updateVolume(e) {
            this.volume = e.target.value
            this.$refs.player.volume = this.volume

            if (this.volume == 0) {
                this.muted = true
            }
            if (this.muted && this.volume > 0) {
                this.muted = false
            }
        },

        timelineClicked(e) {
            const rect = this.$refs.videoProgress.getBoundingClientRect()
            const pos = (e.pageX - rect.left) / this.$refs.videoProgress.offsetWidth
            this.$refs.player.currentTime = pos * this.$refs.player.duration
        },

        handleFullscreen() {
            if (document.fullscreenElement !== null) {
                document.exitFullscreen()
            } else {
                this.$refs.videoContainer.requestFullscreen()
            }
        },

        mousemoveVideo() {
            if (this.playing) {
                this.resetControlsTimeout()
            } else {
                this.controls = true
                clearTimeout(this.controlsHideTimeout)
            }
        },

        videoEnded() {
            this.ended = true
            this.playing = false
            this.$refs.player.currentTime = 0
        },

        resetControlsTimeout() {
            this.controls = true
            clearTimeout(this.controlsHideTimeout)
            this.controlsHideTimeout = setTimeout(() => {
                this.controls = false
            }, this.autoHideControlsDelay)
        },

        formatTime(timeInSeconds) {
            const result = new Date(timeInSeconds * 1000).toISOString().substring(11, 19)

            return {
                minutes: result.substring(3, 5),
                seconds: result.substring(6, 8),
            }
        },
    }))
})
