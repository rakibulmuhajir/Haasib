<script setup lang="ts">
/**
 * DocumentStamp -- the company stamp and optional signature at the foot of a
 * final document: bottom right, stamp above a signature line, signer name and
 * title under it. The server only sends `stamp` for a final document of a
 * type the company ticked (CompanyLetterhead::stampFor), so this component
 * renders whatever it is given and never decides eligibility itself.
 */
export interface DocumentStampData {
    stampUrl?: string | null
    signatureUrl?: string | null
    signerName?: string | null
    signerTitle?: string | null
}

// onScreen: shown on the page too -- a preview of what prints. Otherwise it prints only.
defineProps<{ stamp?: DocumentStampData | null; onScreen?: boolean }>()
</script>

<template>
    <div v-if="stamp && (stamp.stampUrl || stamp.signatureUrl)" class="doc-stamp" :class="{ 'doc-stamp--screen': onScreen }">
        <div class="doc-stamp__block">
            <img v-if="stamp.stampUrl" :src="stamp.stampUrl" alt="Company stamp" class="doc-stamp__stamp" />
            <img v-if="stamp.signatureUrl" :src="stamp.signatureUrl" alt="Signature" class="doc-stamp__signature" />
            <div class="doc-stamp__line">
                <p v-if="stamp.signerName" class="doc-stamp__name">{{ stamp.signerName }}</p>
                <p v-if="stamp.signerTitle" class="doc-stamp__title">{{ stamp.signerTitle }}</p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.doc-stamp {
    /* Print only: the stamp is for documents that are sent, not the screen. */
    display: none;
    justify-content: flex-end;
    margin-top: 32px;
    /* A page break through the stamp splits it from the signature line. */
    break-inside: avoid;
}

.doc-stamp__block {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    min-width: 180px;
}

.doc-stamp__stamp {
    max-width: 120px;
    max-height: 120px;
    object-fit: contain;
    opacity: 0.85;
}

.doc-stamp__signature {
    max-width: 160px;
    max-height: 60px;
    object-fit: contain;
}

.doc-stamp__line {
    width: 100%;
    padding-top: 4px;
    border-top: 1px solid var(--rule-emphasis);
    text-align: center;
}

.doc-stamp__name {
    font-size: 13px;
    font-weight: 600;
}

.doc-stamp__title {
    font-size: 12px;
    color: var(--text-secondary);
}

.doc-stamp--screen {
    display: flex;
}

@media print {
    .doc-stamp {
        display: flex;
    }

    .doc-stamp__stamp,
    .doc-stamp__signature {
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }
}
</style>
