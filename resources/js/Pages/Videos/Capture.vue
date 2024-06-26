<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Textarea from '@/Components/Textarea.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, reactive, computed, watch, onMounted } from 'vue'
import dayjs from 'dayjs'
import HugeUploader from 'huge-uploader';

onMounted(() => {
    Echo.channel(`video-capture`)
        .listen('VideoEncodingStart', () => {
            state.upload.encoding = true
        })
        .listen('VideoEncodingProgress', (e) => {
            state.upload.encodingProgress = parseInt(e.percentage)
        })
})

const state = reactive({
    stream: null,
    audioStream: null,
    recorder: null,
    blob: null,
    blobUrl: computed(() => state.blob ? URL.createObjectURL(state.blob) : null),
    streamActive: computed(() => state.stream?.active),
    isRecording: computed(() => state.recorder ? state.recorder.state === 'recording' : false),
    upload: {
        uploading: ref(false),
        uploadingProgress: ref(null),
        encoding: ref(false),
        encodingProgress: ref(null),
        paused: ref(false),
        target: ref(null),
        video: null
    }
})

const form = useForm({
    title: '',
    description: '',
    video: null
})

const player = ref(null)
const videoPreview = ref(null)
const shouldCaptureAudio = ref(true)
const currentDate = computed(() => dayjs().format('YYYY-MM-DD'))

const startRecording = () => {
    let chunks = []

    const stream = new MediaStream([
        ...state.stream.getTracks(),
        ...(state.audioStream ? state.audioStream.getTracks() : [])
    ])

    state.recorder = new MediaRecorder(stream)

    state.recorder.ondataavailable = event => {
        if (!event.data || event.data.size <= 0) {
            return
        }

        chunks.push(event.data)
    }

    state.recorder.onstop = () => {
        const blob = new Blob(chunks, {
            type: 'video/mp4'
        })

        state.blob = blob

        chunks = []
    }

    state.recorder.start(300)
}

const stopRecording = () => {
    state.recorder.stream.getTracks().forEach(track => track.stop())
    state.stream = null
    state.recorder = null
    state.audioStream = null
}

const captureWebcam = () => {
    if (shouldCaptureAudio.value === true) {
        captureAudio()
    }

    navigator.mediaDevices.getUserMedia({
        video: true,
        audio: false
    }).then((stream) => {
        state.stream = stream
    })
}

const captureScreen = () => {
    if (shouldCaptureAudio.value === true) {
        captureAudio()
    }

    navigator.mediaDevices.getDisplayMedia({
        video: true,
        audio: false
    }).then((stream) => {
        state.stream = stream
    })
}

const captureAudio = () => {
    navigator.mediaDevices.getUserMedia({
        video: false,
        audio: {
            echoCancellation: true,
            noiseSuppression: true,
            autoGainControl: true,
        }
    }).then((stream) => {
        state.audioStream = stream
    })
}

const handleFileUpload = (id) => {
    const upload = new HugeUploader({ endpoint: route('videos.capture.file', state.upload.video), file: form.video,
        headers: {
            'X-CSRF-TOKEN': usePage().props.csrf_token
        },
        chunkSize: 100 / 1024
    });

    upload.on('progress', (progress) => {
        state.upload.uploadingProgress = progress.detail
    });

    upload.on('error', () => {
        state.upload.target = ref(null)
        state.upload.uploading = ref(false)
    });

    upload.on('finish', () => { });

    upload.on('offline', () => { state.upload.targer = ref(null) });

    return upload
}

const handleCapture = () => {
    axios.post(route('videos.capture.store'), {
            title: form.title,
            description: form.description
        }).then((response) => {
            state.upload.encodingProgress = ref(0)
            state.upload.video = response.data.video
            state.upload.target = handleFileUpload(response.data.id)
            state.upload.uploading = true
            state.upload.encodingProgress = 0
    })
}

const cancelFileUpload = () => {
    router.delete(route('videos.destroy', state.upload.video), { onSuccess: () => {
        state.blob = null
        state.upload.uploading = ref(false)
        state.upload.uploadingProgress = ref(0)
    }})
}

const pauseCapture = () => {
    state.upload.paused = true
    state.upload.target.togglePause()
}

watch(() => state.stream, (stream) => {
    if (stream !== null) {
        player.value.srcObject = stream

    }
})

watch(() => state.blobUrl, (url) => {
    if (url !== null) {
        videoPreview.value.src = url

    }
})

watch(() => state.blob, (blob) => {
    if (blob !== null) {
            form.video = new File([blob], 'video.mp4', {
            type: 'video/mp4'
        })

        form.title = currentDate.value
        form.description = `A video captured on ${currentDate.value}`
    }
})
</script>

<template>
    <Head title="Capture" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <div class="max-w-[240px] w-full space-y-3">

                            <div class="space-y-1" v-if="state.upload.encoding">
                                <div class="bg-gray-100 shadow-inner h-3 rounded overflow-hidden">
                                    <div class="bg-green-500 h-full" v-bind:style="{ width: `${state.upload.encodingProgress}% ` }"></div>
                                </div>
                                <div class="text-sm">
                                    Encoding
                                </div>
                            </div>

                            <div class="space-y-1" v-if="state.upload.uploading">
                                <div class="bg-gray-100 shadow-inner h-3 rounded overflow-hidden">
                                    <div class="bg-blue-500 h-full" v-bind:style="{ width: `${state.upload.uploadingProgress}% ` }"></div>
                                </div>
                                <div class="text-sm">
                                    Uploading
                                </div>
                            </div>

                            <div class="flex items-center space-x-3" v-if="state.upload.uploading && state.upload.uploadingProgress < 100">
                                <button class="text-blue-500 text-sm font-medium" v-on:click="pauseCapture()">
                                    <span v-if="!state.upload.paused">Pause</span>
                                    <span v-if="state.upload.paused === true">Resume</span>
                                </button>
                                <button class="text-blue-500 text-sm font-medium" v-on:click="cancelFileUpload()">
                                    Cancel upload
                                </button>
                            </div>

                        </div>
                        <form v-show="state.blobUrl" class="space-y-6" v-on:submit.prevent="handleCapture()">
                            <video controls ref="videoPreview"></video>
                            <div>
                                <InputLabel for="title" value="Title" />
                                <TextInput id="title" type="text" class="mt-1 block w-full" v-model="form.title" />
                                <InputError class="mt-2" :message="form.errors.title" />
                            </div>
                            <div>
                                <InputLabel for="description" value="Description" />
                                <Textarea id="description" type="text" class="mt-1 block w-full" v-model="form.description" />
                                <InputError class="mt-2" :message="form.errors.description" />
                            </div>
                            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Save video
                            </PrimaryButton>
                        </form>

                        <div v-show="!state.blobUrl">
                            <div v-show="state.streamActive" class="space-y-6">
                                <video ref="player" autoplay></video>

                                <div class="flex justify-center">
                                    <PrimaryButton v-on:click="startRecording" v-if="!state.isRecording">
                                        Start recording
                                    </PrimaryButton>

                                    <DangerButton v-on:click="stopRecording" v-if="state.isRecording">
                                        Stop recording
                                    </DangerButton>
                                </div>
                            </div>

                            <div class="flex items-center justify-center space-x-4" v-if="!state.streamActive">
                                <PrimaryButton v-on:click="captureWebcam">
                                    Capture webcam
                                </PrimaryButton>

                                <PrimaryButton v-on:click="captureScreen">
                                    Capture screen
                                </PrimaryButton>

                                <div class="space-x-2 flex items-center">
                                    <Checkbox id="audio" v-model:checked="shouldCaptureAudio" />
                                    <label for="audio" class="font-medium text-sm">Enable audio</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
